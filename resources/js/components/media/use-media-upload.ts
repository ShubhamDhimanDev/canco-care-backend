import { useCallback, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Admin/MediaController';
import { postFormData } from '@/lib/http';
import type { MediaItem } from '@/types';

export type UploadEntry = {
    key: string;
    name: string;
    status: 'uploading' | 'done' | 'error';
    message?: string;
};

/**
 * Uploads images one at a time to the media library (the server converts each to WebP) and tracks
 * the result of every file, so one bad file does not stop the rest.
 */
export function useMediaUpload(onUploaded?: (item: MediaItem) => void) {
    const [entries, setEntries] = useState<UploadEntry[]>([]);
    const [busy, setBusy] = useState(false);

    const upload = useCallback(
        async (files: FileList | File[]) => {
            const list = Array.from(files);

            if (list.length === 0) {
                return [] as MediaItem[];
            }

            const created: MediaItem[] = [];
            const batch = list.map((file, index) => ({
                key: `${Date.now()}-${index}-${file.name}`,
                file,
            }));

            setBusy(true);
            setEntries(
                batch.map(({ key, file }) => ({
                    key,
                    name: file.name,
                    status: 'uploading',
                })),
            );

            for (const { key, file } of batch) {
                const body = new FormData();
                body.append('file', file);

                const result = await postFormData<MediaItem>(
                    MediaController.store.url(),
                    body,
                );

                if (result.ok) {
                    created.push(result.data);
                    onUploaded?.(result.data);
                }

                setEntries((current) =>
                    current.map((entry) =>
                        entry.key === key
                            ? result.ok
                                ? { ...entry, status: 'done' }
                                : {
                                      ...entry,
                                      status: 'error',
                                      message: result.message,
                                  }
                            : entry,
                    ),
                );
            }

            setBusy(false);

            return created;
        },
        [onUploaded],
    );

    const clear = useCallback(() => setEntries([]), []);

    return { entries, busy, upload, clear };
}
