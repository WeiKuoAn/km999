<?php

namespace App\Http\Controllers;

use App\Models\FeeDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeeDiscountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('FeeDiscounts/Index', [
            'discounts' => FeeDiscount::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(50)
                ->through(fn (FeeDiscount $row): array => $this->payload($row)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('FeeDiscounts/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        FeeDiscount::query()->create($validated);

        return to_route('fee-discounts.index')
            ->with('success', '已新增優惠。');
    }

    public function edit(FeeDiscount $feeDiscount): Response
    {
        return Inertia::render('FeeDiscounts/Edit', [
            'discount' => $this->payload($feeDiscount),
        ]);
    }

    public function update(Request $request, FeeDiscount $feeDiscount): RedirectResponse
    {
        $feeDiscount->update($this->validated($request));

        return to_route('fee-discounts.index')
            ->with('success', '已更新優惠。');
    }

    public function destroy(FeeDiscount $feeDiscount): RedirectResponse
    {
        if ($feeDiscount->reconciliations()->exists()) {
            $feeDiscount->update(['is_active' => false]);

            return to_route('fee-discounts.index')
                ->with('success', '此優惠已有收款紀錄，已改為停用（未刪除）。');
        }

        $feeDiscount->delete();

        return to_route('fee-discounts.index')
            ->with('success', '已刪除優惠。');
    }

    /**
     * @return array{
     *   name:string,
     *   type:string,
     *   value:int,
     *   is_active:bool,
     *   sort_order:int,
     *   starts_on:?string,
     *   ends_on:?string,
     *   notes:?string
     * }
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([FeeDiscount::TYPE_AMOUNT, FeeDiscount::TYPE_PERCENT])],
            'value' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['type'] === FeeDiscount::TYPE_PERCENT) {
            $request->validate([
                'value' => ['integer', 'min:1', 'max:100'],
            ]);
            $validated['value'] = min(100, (int) $validated['value']);
        }

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'value' => (int) $validated['value'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'starts_on' => ! empty($validated['starts_on']) ? $validated['starts_on'] : null,
            'ends_on' => ! empty($validated['ends_on']) ? $validated['ends_on'] : null,
            'notes' => ! empty($validated['notes']) ? $validated['notes'] : null,
        ];
    }

    /**
     * @return array{
     *   id:int,
     *   name:string,
     *   type:string,
     *   value:int,
     *   is_active:bool,
     *   sort_order:int,
     *   starts_on:?string,
     *   ends_on:?string,
     *   notes:?string,
     *   label:string
     * }
     */
    private function payload(FeeDiscount $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'type' => $row->type,
            'value' => (int) $row->value,
            'is_active' => (bool) $row->is_active,
            'sort_order' => (int) $row->sort_order,
            'starts_on' => $row->starts_on?->toDateString(),
            'ends_on' => $row->ends_on?->toDateString(),
            'notes' => $row->notes,
            'label' => $row->label(),
        ];
    }
}
