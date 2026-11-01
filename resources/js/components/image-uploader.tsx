import { ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { StoryImage } from '@/types';

type Props = {
    current: StoryImage | null;
    error?: string;
    altError?: string;
};

const MIN_WIDTH = 800;

/**
 * Featured-image field: pick a file, see it, describe it (alt text is required) or
 * remove the current one. Submits `featured_image`, `featured_image_alt` and
 * `remove_image` with the surrounding form.
 */
export default function ImageUploader({ current, error, altError }: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [removeCurrent, setRemoveCurrent] = useState(false);

    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const shown = preview ?? (removeCurrent ? null : (current?.url ?? null));
    const hasImage = shown !== null;
    const tooSmall =
        preview === null &&
        !removeCurrent &&
        current?.width != null &&
        current.width < MIN_WIDTH;

    const clearSelection = () => {
        if (fileInput.current) {
            fileInput.current.value = '';
        }

        setPreview(null);
    };

    return (
        <div className="space-y-3">
            <input
                type="hidden"
                name="remove_image"
                value={removeCurrent && preview === null ? '1' : '0'}
            />

            {shown && (
                <img
                    src={shown}
                    alt=""
                    className="max-h-48 w-full rounded-md border bg-muted object-contain"
                />
            )}

            {tooSmall && current?.width != null && (
                <p className="text-xs text-amber-700 dark:text-amber-400">
                    This image is only {current.width} px wide, so it may look
                    soft on the website. Upload a larger one if you have it.
                </p>
            )}

            <div className="grid gap-2">
                <Label htmlFor="featured_image">
                    {hasImage ? 'Replace image' : 'Image'}
                </Label>
                <Input
                    ref={fileInput}
                    id="featured_image"
                    name="featured_image"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    aria-describedby="featured_image_help"
                    aria-invalid={error ? true : undefined}
                    onChange={(event) => {
                        const file = event.target.files?.[0];

                        setPreview(file ? URL.createObjectURL(file) : null);

                        if (file) {
                            setRemoveCurrent(false);
                        }
                    }}
                />
                <p
                    id="featured_image_help"
                    className="text-xs text-muted-foreground"
                >
                    JPG, PNG or WebP, at least {MIN_WIDTH} px wide, up to 5 MB.
                </p>
                <InputError message={error} />
            </div>

            {hasImage && (
                <div className="grid gap-2">
                    <Label htmlFor="featured_image_alt">
                        Image description (alt text)
                    </Label>
                    <Input
                        id="featured_image_alt"
                        name="featured_image_alt"
                        defaultValue={current?.alt ?? ''}
                        maxLength={200}
                        required
                        placeholder="What is in the picture?"
                        aria-invalid={altError ? true : undefined}
                    />
                    <InputError message={altError} />
                </div>
            )}

            {preview !== null && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={clearSelection}
                >
                    <ImagePlus /> Clear new image
                </Button>
            )}

            {preview === null && current && !removeCurrent && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setRemoveCurrent(true)}
                >
                    <Trash2 /> Remove image
                </Button>
            )}

            {removeCurrent && preview === null && (
                <p className="text-sm text-muted-foreground">
                    The image will be removed when you save.{' '}
                    <button
                        type="button"
                        className="underline underline-offset-4"
                        onClick={() => setRemoveCurrent(false)}
                    >
                        Undo
                    </button>
                </p>
            )}
        </div>
    );
}
