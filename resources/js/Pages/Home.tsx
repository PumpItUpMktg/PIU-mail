import { Head } from '@inertiajs/react';

// Placeholder only. Relay 02 replaces this with the Target Area map.
export default function Home({ appName }: { appName: string }) {
    return (
        <>
            <Head title="Home" />
            <main className="flex min-h-screen items-center justify-center bg-slate-50 p-4">
                <div className="text-center">
                    <h1 className="text-2xl font-semibold text-slate-900">{appName}</h1>
                    <p className="mt-2 text-slate-600">The Target Area map arrives in relay 02.</p>
                </div>
            </main>
        </>
    );
}
