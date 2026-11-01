import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import type { UploadEntry } from '@/components/media/use-media-upload';

/** One line per file of the current upload, announced politely to screen readers. */
export default function UploadStatus({ entries }: { entries: UploadEntry[] }) {
    if (entries.length === 0) {
        return null;
    }

    return (
        <ul
            className="space-y-1 text-sm"
            aria-live="polite"
            aria-label="Upload progress"
        >
            {entries.map((entry) => (
                <li key={entry.key} className="flex items-start gap-2">
                    {entry.status === 'uploading' && (
                        <Spinner className="mt-0.5 size-4" />
                    )}
                    {entry.status === 'done' && (
                        <CheckCircle2 className="mt-0.5 size-4 text-emerald-600" />
                    )}
                    {entry.status === 'error' && (
                        <AlertCircle className="mt-0.5 size-4 text-destructive" />
                    )}
                    <span>
                        <span className="font-medium">{entry.name}</span>
                        {entry.status === 'uploading' &&
                            ' — converting to WebP…'}
                        {entry.status === 'done' && ' — uploaded'}
                        {entry.status === 'error' && (
                            <span className="text-destructive">
                                {' '}
                                — {entry.message}
                            </span>
                        )}
                    </span>
                </li>
            ))}
        </ul>
    );
}
