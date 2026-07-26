/**
 * Fortify 註冊路由（停用註冊時 Wayfinder 不會產生 routes/register）。
 * 僅在後端重新啟用 Features::registration() 時才有對應 POST。
 */
export function registerForm(): { action: string; method: 'post' } {
    return {
        action: '/register',
        method: 'post',
    };
}

export function registerUrl(): string {
    return '/register';
}
