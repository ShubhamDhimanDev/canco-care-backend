import { ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import MediaPicker from '@/components/media/media-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInputNotifier } from '@/hooks/use-form-dirty';
import type { AttachedImage, MediaItem } from '@/types';

type Props = {
    /** The form field names the server reads. `remove` is optional (home-page images just clear). */
    fields: { mediaId: string; alt: string; remove?: string };
    current: AttachedImage | null;
    label?: string;
    /** A description is required whenever an image is set (stories and events). */
    altRequired?: boolean;
    /** Smaller than this and the editor is warned the image may look soft. */
    minWidth?: number;
    error?: string;
    altError?: string;
    /** Called whenever an image becomes set or cleared (for live checklists). */
    onPresenceChange?: (present: boolean) => void;
    /** Called with the address of the image now shown, or null (for live previews). */
    onImageChange?: (url: string | null) => void;
    /** Shape of the preview frame; defaults to the image's own proportions. */
    previewClassName?: string;
};

/**
 * An image field backed by the media library: choose or upload an image, describe it (alt text),
 * or remove it. It submits `fields.mediaId` (set only when a new one is chosen), `fields.alt` and,
 * when given, `fields.remove` with the surrounding form.
 */
export default function ImagePicker({
    fields,
    current,
    label = 'Image',
    altRequired = false,
    minWidth,
    error,
    altError,
    onPresenceChange,
    onImageChange,
    previewClassName,
}: Props) {
    const altId = useId();
    const [open, setOpen] = useState(false);
    const [chosen, setChosen] = useState<MediaItem | null>(null);
    const [removed, setRemoved] = useState(false);

    const shown = chosen
        ? { url: chosen.thumb_url, full: chosen.url, width: chosen.width }
        : removed
          ? null
          : current
            ? { url: current.url, full: current.url, width: current.width }
            : null;

    const present = shown !== null;
    const shownUrl = shown?.full ?? null;
    const mediaIdRef = useInputNotifier(chosen ? String(chosen.id) : '');
    const removeRef = useInputNotifier(removed && !chosen ? '1' : '0');

    // Callers often pass inline functions; keep the latest in a ref so the effects below only run
    // when the image itself changes (otherwise a caller that sets state would loop).
    const callbacks = useRef({ onPresenceChange, onImageChange });

    useEffect(() => {
        callbacks.current = { onPresenceChange, onImageChange };
    });

    useEffect(() => {
        callbacks.current.onPresenceChange?.(present);
    }, [present]);

    useEffect(() => {
        callbacks.current.onImageChange?.(shownUrl);
    }, [shownUrl]);

    const tooSmall =
        minWidth !== undefined &&
        shown?.width != null &&
        shown.width < minWidth;

    return (
        <div className="space-y-3">
            <input
                ref={mediaIdRef}
                type="hidden"
                name={fields.mediaId}
                value={chosen ? chosen.id : ''}
            />
            {fields.remove && (
                <input
                    ref={removeRef}
                    type="hidden"
                    name={fields.remove}
                    value={removed && !chosen ? '1' : '0'}
                />
            )}

            <Label>{label}</Label>

            {shown ? (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    aria-label={`${label}: change image`}
                    className="group relative block w-full overflow-hidden rounded-lg border bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <img
                        src={shown.url}
                        alt=""
                        className={
                            previewClassName ?? 'max-h-52 w-full object-contain'
                        }
                    />
                    <span className="absolute inset-0 grid place-items-center bg-black/45 text-sm font-medium text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                        Change image
                    </span>
                </button>
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="grid h-32 w-full place-items-center rounded-lg border-2 border-dashed text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <span className="grid justify-items-center gap-1">
                        <ImagePlus className="size-6" />
                        Choose or upload an image
                    </span>
                </button>
            )}

            {tooSmall && (
                <p className="text-xs text-amber-700 dark:text-amber-400">
                    This image is only {shown?.width} px wide, so it may look
                    soft on the website. Choose a larger one if you have it.
                </p>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    onClick={() => setOpen(true)}
                >
                    <ImagePlus /> {shown ? 'Change image' : 'Choose image'}
                </Button>
                {shown && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setChosen(null);
                            setRemoved(true);
                        }}
                    >
                        <Trash2 /> Remove
                    </Button>
                )}
            </div>
            <InputError message={error} />

            {shown && (
                <div className="grid gap-2">
                    <Label htmlFor={altId}>
                        Image description (alt text)
                        {altRequired ? '' : ' — optional'}
                    </Label>
                    <Input
                        // A new choice starts from that image's own description.
                        key={chosen?.id ?? 'current'}
                        id={altId}
                        name={fields.alt}
                        defaultValue={
                            chosen ? (chosen.alt ?? '') : (current?.alt ?? '')
                        }
                        maxLength={200}
                        required={altRequired}
                        placeholder="What is in the picture?"
                        aria-invalid={altError ? true : undefined}
                    />
                    <InputError message={altError} />
                </div>
            )}

            <MediaPicker
                open={open}
                onOpenChange={setOpen}
                onSelect={(item) => {
                    setChosen(item);
                    setRemoved(false);
                }}
            />
        </div>
    );
}
