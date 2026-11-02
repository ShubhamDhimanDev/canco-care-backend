import { Check, Search, Upload } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { getJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { MediaItem } from '@/types';

type Feed = {
    data: MediaItem[];
    meta: { current_page: number; last_page: number; total: number };
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** `alt` is only given when `withAlt` is on: the description the editor confirmed. */
    onSelect: (item: MediaItem, alt?: string) => void;
    title?: string;
    /**
     * Two-step mode for putting an image into running text: a click selects it, an alt-text field
     * appears, and `confirmLabel` inserts it. Without it a click chooses the image straight away.
     */
    withAlt?: boolean;
    confirmLabel?: string;
};

const formatSize = (bytes: number): string =>
    bytes >= 1_000_000
        ? `${(bytes / 1_000_000).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1000))} KB`;

/**
 * "Choose an image" dialog: browse or search the media library, or upload a new image (which is
 * converted to WebP, added to the library and selected in one step). Files can also be dropped on it.
 */
export default function MediaPicker({
    open,
    onOpenChange,
    onSelect,
    title = 'Choose an image',
    withAlt = false,
    confirmLabel = 'Use this image',
}: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [search, setSearch] = useState('');
    const [items, setItems] = useState<MediaItem[]>([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [selected, setSelected] = useState<MediaItem | null>(null);
    const [alt, setAlt] = useState('');
    const [dragging, setDragging] = useState(false);

    const choose = useCallback(
        (item: MediaItem, description?: string) => {
            onSelect(item, description);
            onOpenChange(false);
        },
        [onSelect, onOpenChange],
    );

    // A click either chooses (single-step) or selects and waits for the editor to confirm.
    const pick = useCallback(
        (item: MediaItem) => {
            if (!withAlt) {
                choose(item);

                return;
            }

            setSelected(item);
            setAlt(item.alt ?? '');
        },
        [withAlt, choose],
    );

    const { entries, busy, upload } = useMediaUpload();

    const uploadFiles = async (files: FileList | File[]) => {
        const [created] = await upload(files);

        if (created) {
            pick(created);
        }
    };

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

    // Each time the dialog opens, start without a selection.
    useEffect(() => {
        if (open) {
            setSelected(null);
        }
    }, [open]);

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
            <DialogContent
                className="max-h-[90vh] gap-4 overflow-y-auto sm:max-w-3xl"
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);

                    if (event.dataTransfer.files.length > 0) {
                        void uploadFiles(event.dataTransfer.files);
                    }
                }}
            >
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>
                    Pick one from the library, or upload a new one — drag a file
                    here too. Uploads are converted to WebP automatically.
                </DialogDescription>

                {dragging && (
                    <div className="pointer-events-none absolute inset-2 z-20 grid place-items-center rounded-lg border-2 border-dashed border-primary bg-background/90 text-sm font-medium">
                        Drop to upload
                    </div>
                )}

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

                            await uploadFiles(files);
                            event.target.value = '';
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
                    <p
                        role="alert"
                        className="text-sm text-destructive-foreground"
                    >
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
                    {items.map((item) => {
                        const isSelected = selected?.id === item.id;

                        return (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    onClick={() => pick(item)}
                                    onDoubleClick={() =>
                                        choose(item, item.alt ?? '')
                                    }
                                    aria-pressed={
                                        withAlt ? isSelected : undefined
                                    }
                                    className={cn(
                                        'group relative block w-full overflow-hidden rounded-md border text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                        isSelected &&
                                            'border-primary ring-2 ring-primary',
                                    )}
                                >
                                    <img
                                        src={item.thumb_url}
                                        alt=""
                                        loading="lazy"
                                        className="aspect-[4/3] w-full bg-muted object-cover transition-opacity group-hover:opacity-80"
                                    />
                                    {isSelected && (
                                        <span className="absolute top-1.5 right-1.5 grid size-5 place-items-center rounded-full bg-primary text-primary-foreground">
                                            <Check className="size-3.5" />
                                        </span>
                                    )}
                                    <span className="block truncate px-2 pt-1 text-xs">
                                        {item.name}
                                    </span>
                                    <span className="block px-2 pb-1 text-[11px] text-muted-foreground">
                                        {item.width} × {item.height}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
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

                {withAlt && (
                    <div className="sticky -bottom-6 -mx-6 -mb-6 flex flex-wrap items-end gap-3 border-t bg-background px-6 py-4">
                        {selected ? (
                            <>
                                <img
                                    src={selected.thumb_url}
                                    alt=""
                                    className="size-14 rounded-md border object-cover"
                                />
                                <div className="grid min-w-52 flex-1 gap-1.5">
                                    <Label htmlFor="picker-alt">
                                        Describe the picture for people who
                                        cannot see it
                                    </Label>
                                    <Input
                                        id="picker-alt"
                                        value={alt}
                                        onChange={(event) =>
                                            setAlt(event.target.value)
                                        }
                                        maxLength={200}
                                        placeholder="What is in the picture?"
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                choose(selected, alt.trim());
                                            }
                                        }}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {selected.width} × {selected.height} px
                                        · {formatSize(selected.size_bytes)}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    onClick={() => choose(selected, alt.trim())}
                                >
                                    {confirmLabel}
                                </Button>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Select a picture to continue.
                            </p>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
