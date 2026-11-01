import { Search, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Admin/MediaController';
import UploadStatus from '@/components/media/upload-status';
import { useMediaUpload } from '@/components/media/use-media-upload';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { getJson } from '@/lib/http';
import type { MediaItem } from '@/types';

type Feed = {
    data: MediaItem[];
    meta: { current_page: number; last_page: number; total: number };
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (item: MediaItem) => void;
};

/**
 * "Choose an image" dialog: browse or search the media library, or upload a new image (which is
 * converted to WebP, added to the library and selected in one step).
 */
export default function MediaPicker({ open, onOpenChange, onSelect }: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [search, setSearch] = useState('');
    const [items, setItems] = useState<MediaItem[]>([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    const choose = useCallback(
        (item: MediaItem) => {
            onSelect(item);
            onOpenChange(false);
        },
        [onSelect, onOpenChange],
    );

    const { entries, busy, upload } = useMediaUpload();

    const load = useCallback(async (term: string, pageNumber: number) => {
        setLoading(true);
        setFailed(false);

        try {
            const feed = await getJson<Feed>(
                MediaController.library.url({
                    query: { search: term || undefined, page: pageNumber },
                }),
            );

            setItems((current) =>
                pageNumber === 1 ? feed.data : [...current, ...feed.data],
            );
            setPage(feed.meta.current_page);
            setLastPage(feed.meta.last_page);
        } catch {
            setFailed(true);
        } finally {
            setLoading(false);
        }
    }, []);

    // Reload (debounced) whenever the dialog opens or the search text changes.
    useEffect(() => {
        if (!open) {
            return;
        }

        const timer = setTimeout(() => void load(search, 1), 250);

        return () => clearTimeout(timer);
    }, [open, search, load]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogTitle>Choose an image</DialogTitle>
                <DialogDescription>
                    Pick one from the library, or upload a new one — it is
                    converted to WebP automatically.
                </DialogDescription>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-48 flex-1">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by name or description"
                            aria-label="Search the media library"
                            className="pl-8"
                        />
                    </div>
                    <input
                        ref={fileInput}
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        className="sr-only"
                        tabIndex={-1}
                        aria-label="Upload an image"
                        onChange={async (event) => {
                            const files = event.target.files;

                            if (!files || files.length === 0) {
                                return;
                            }

                            const [created] = await upload(files);
                            event.target.value = '';

                            if (created) {
                                choose(created);
                            }
                        }}
                    />
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={busy}
                        onClick={() => fileInput.current?.click()}
                    >
                        {busy ? <Spinner /> : <Upload />} Upload new
                    </Button>
                </div>

                <UploadStatus entries={entries} />

                {failed && (
                    <p role="alert" className="text-sm text-destructive">
                        The library could not be loaded. Close this window and
                        try again.
                    </p>
                )}

                {!failed && !loading && items.length === 0 && (
                    <p className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {search
                            ? `No images match “${search}”.`
                            : 'The library is empty. Upload your first image.'}
                    </p>
                )}

                <ul className="m-0 grid list-none grid-cols-2 gap-3 p-0 sm:grid-cols-3 md:grid-cols-4">
                    {items.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                onClick={() => choose(item)}
                                className="group block w-full overflow-hidden rounded-md border text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <img
                                    src={item.thumb_url}
                                    alt=""
                                    loading="lazy"
                                    className="aspect-[4/3] w-full bg-muted object-cover transition-opacity group-hover:opacity-80"
                                />
                                <span className="block truncate px-2 py-1 text-xs">
                                    {item.name}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>

                {loading && (
                    <div className="flex justify-center p-2">
                        <Spinner />
                    </div>
                )}

                {!loading && page < lastPage && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => void load(search, page + 1)}
                    >
                        Load more
                    </Button>
                )}
            </DialogContent>
        </Dialog>
    );
}
