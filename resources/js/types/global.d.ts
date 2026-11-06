import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            isAdmin: boolean;
            /** Every permission the signed-in person holds (all of them for a super administrator). */
            permissions: string[];
            unreadInquiries: number;
            [key: string]: unknown;
        };
    }
}
