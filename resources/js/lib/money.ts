/** 5000 → "$50.00" (Canadian dollars; the admin always shows cents). */
export function formatCents(cents: number): string {
    return new Intl.NumberFormat('en-CA', {
        style: 'currency',
        currency: 'CAD',
    }).format(cents / 100);
}
