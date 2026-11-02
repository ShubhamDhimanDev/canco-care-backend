import { Form, Head, Link, router } from '@inertiajs/react';
import { Copy, Search, Upload, Wand2 } from 'lucide-react';
import { useRef, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Admin/MediaController';
import Heading from '@/components/heading';
import UploadStatus from '@/components/media/upload-status';
import { useMediaUpload } from '@/components/media/use-media-upload';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatBytes } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { MediaItem, Paginated } from '@/types';

type Props = {
    media: Paginated<MediaItem>;
    filters: { search: string; format: 'all' | 'other' };
    counts: { all: number; other: number };
    canConvert: boolean;
};

function EditDialog({ item }: { item: MediaItem }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Details
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{item.name}</DialogTitle>
                <DialogDescription>
                    {item.width} × {item.height} px ·{' '}
                    {formatBytes(item.size_bytes)} ·{' '}
                    {item.is_webp ? 'WebP' : 'not converted yet'}
                </DialogDescription>
                <img
                    src={item.thumb_url}
                    alt=""
                    className="max-h-48 w-full rounded-md border bg-muted object-contain"
                />
                <Form
                    {...MediaController.update.form(item.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`alt-${item.id}`}>
                                    Default image description (alt text)
                                </Label>
                                <Input
                                    id={`alt-${item.id}`}
                                    name="alt"
                                    defaultValue={item.alt ?? ''}
                                    maxLength={200}
                                    placeholder="What is in the picture?"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Used whenever this image is picked and no
                                    other description is typed.
                                </p>
                                {errors.alt && (
                                    <p className="text-sm text-destructive">
                                        {errors.alt}
                                    </p>
                                )}
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Close
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />} Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({ item }: { item: MediaItem }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="text-destructive">
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{item.name}”?</DialogTitle>
                <DialogDescription>
                    The file is removed from the library and the server. If it
                    is used on a story, event or the home page it cannot be
                    deleted until you remove it there.
                </DialogDescription>
                <Form
                    {...MediaController.destroy.form(item.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete image
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function MediaIndex({
    media,
    filters,
    counts,
    canConvert,
}: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [, copy] = useClipboard();
    const { entries, busy, upload, clear } = useMediaUpload();

    const send = async (files: FileList | File[]) => {
        await upload(files);
        router.reload({ only: ['media', 'counts'] });
    };

    const tabs = [
        { key: 'all', label: 'All', count: counts.all },
        { key: 'other', label: 'Not WebP yet', count: counts.other },
    ] as const;

    return (
        <>
            <Head title="Media" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Media"
                        description="Every image on the website lives here. Uploads are converted to WebP automatically, so pages load faster."
                    />
                    <Button
                        type="button"
                        disabled={busy}
                        onClick={() => fileInput.current?.click()}
                    >
                        {busy ? <Spinner /> : <Upload />} Upload images
                    </Button>
                    <input
                        ref={fileInput}
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        className="sr-only"
                        tabIndex={-1}
                        aria-label="Choose images to upload"
                        onChange={(event) => {
                            if (event.target.files) {
                                void send(event.target.files);
                                event.target.value = '';
                            }
                        }}
                    />
                </div>

                {!canConvert && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive/40 p-3 text-sm text-destructive"
                    >
                        This server's PHP cannot write WebP files (GD with WebP
                        support is missing), so uploads are blocked until that
                        is installed.
                    </p>
                )}

                <div
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault();
                        setDragging(false);
                        void send(event.dataTransfer.files);
                    }}
                    className={cn(
                        'rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground transition-colors',
                        dragging && 'border-primary bg-muted',
                    )}
                >
                    Drag images here (JPG, PNG, GIF or WebP, up to 15 MB each).
                    Large photos are scaled down to 2400 px.
                </div>

                {entries.length > 0 && (
                    <div className="space-y-2 rounded-lg border p-4">
                        <UploadStatus entries={entries} />
                        {!busy && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={clear}
                            >
                                Dismiss
                            </Button>
                        )}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav
                        aria-label="Filter images"
                        className="flex flex-wrap gap-1"
                    >
                        {tabs.map((tab) => {
                            const active = filters.format === tab.key;

                            return (
                                <Link
                                    key={tab.key}
                                    href={MediaController.index({
                                        query: {
                                            format:
                                                tab.key === 'all'
                                                    ? undefined
                                                    : tab.key,
                                            search: filters.search || undefined,
                                        },
                                    })}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm transition-colors',
                                        active
                                            ? 'bg-secondary font-medium text-secondary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    {tab.label}{' '}
                                    <span className="text-xs tabular-nums opacity-70">
                                        {tab.count}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    <Form
                        {...MediaController.index.form()}
                        className="flex items-center gap-2"
                        role="search"
                    >
                        {filters.format !== 'all' && (
                            <input
                                type="hidden"
                                name="format"
                                value={filters.format}
                            />
                        )}
                        <Input
                            type="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search name or description"
                            aria-label="Search media"
                            className="w-64"
                        />
                        <Button type="submit" variant="secondary">
                            <Search /> Search
                        </Button>
                    </Form>
                </div>

                {media.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center text-muted-foreground">
                        {filters.search
                            ? `No images match “${filters.search}”.`
                            : filters.format === 'other'
                              ? 'Every image is already WebP.'
                              : 'No images yet. Upload your first one.'}
                    </div>
                ) : (
                    <ul className="m-0 grid list-none grid-cols-2 gap-4 p-0 sm:grid-cols-3 lg:grid-cols-4">
                        {media.data.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-col overflow-hidden rounded-lg border"
                            >
                                <img
                                    src={item.thumb_url}
                                    alt={item.alt ?? ''}
                                    loading="lazy"
                                    className="aspect-[4/3] w-full bg-muted object-cover"
                                />
                                <div className="flex flex-1 flex-col gap-2 p-3">
                                    <div className="min-w-0">
                                        <p
                                            className="truncate text-sm font-medium"
                                            title={item.name}
                                        >
                                            {item.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.width} × {item.height} ·{' '}
                                            {formatBytes(item.size_bytes)}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-1">
                                        {item.is_webp ? (
                                            <Badge className="border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                WebP
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Not WebP
                                            </Badge>
                                        )}
                                        {!item.alt && (
                                            <Badge variant="outline">
                                                No description
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="mt-auto flex flex-wrap gap-1">
                                        <EditDialog item={item} />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => void copy(item.url)}
                                            aria-label={`Copy the address of ${item.name}`}
                                        >
                                            <Copy />
                                        </Button>
                                        {!item.is_webp && canConvert && (
                                            <Form
                                                {...MediaController.convert.form(
                                                    item.id,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="secondary"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        {processing ? (
                                                            <Spinner />
                                                        ) : (
                                                            <Wand2 />
                                                        )}{' '}
                                                        Convert
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                        <DeleteDialog item={item} />
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {media.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Showing {media.from}–{media.to} of {media.total}
                        </span>
                        <div className="flex gap-2">
                            {media.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={media.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {media.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={media.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

MediaIndex.layout = {
    breadcrumbs: [{ title: 'Media', href: MediaController.index() }],
};
