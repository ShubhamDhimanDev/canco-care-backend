import Image from '@tiptap/extension-image';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Trash2,
    Undo2,
    Unlink,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import MediaPicker from '@/components/media/media-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInputNotifier } from '@/hooks/use-form-dirty';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    /** Name of the hidden field that carries the HTML to the server. */
    name: string;
    /** Accessible name of the editing area. */
    label: string;
    defaultValue: string;
    invalid?: boolean;
    describedBy?: string;
    /** Offer "insert image" (library pictures only). On by default. */
    allowImages?: boolean;
    /** Height of the writing area, as a Tailwind class. */
    minHeightClass?: string;
};

/** Turns what an editor typed into an address the server's sanitiser will accept. */
function normaliseUrl(input: string): string {
    const value = input.trim();

    if (/^(https?:\/\/|mailto:|tel:|\/)/i.test(value)) {
        return value;
    }

    return value.includes('@') && !value.includes(' ')
        ? `mailto:${value}`
        : `https://${value}`;
}

function ToolbarButton({
    label,
    active = false,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant={active ? 'secondary' : 'ghost'}
            size="icon"
            className="size-8"
            aria-label={label}
            aria-pressed={active}
            title={label}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function Divider() {
    return <span className="mx-1 h-5 w-px bg-border" aria-hidden="true" />;
}

function Toolbar({
    editor,
    allowImages,
}: {
    editor: Editor;
    allowImages: boolean;
}) {
    const [pickerOpen, setPickerOpen] = useState(false);
    const [linkOpen, setLinkOpen] = useState(false);
    const [linkValue, setLinkValue] = useState('');
    const [linkError, setLinkError] = useState('');

    const state = useEditorState({
        editor,
        selector: ({ editor }) => ({
            bold: editor.isActive('bold'),
            italic: editor.isActive('italic'),
            h2: editor.isActive('heading', { level: 2 }),
            h3: editor.isActive('heading', { level: 3 }),
            bullets: editor.isActive('bulletList'),
            numbers: editor.isActive('orderedList'),
            quote: editor.isActive('blockquote'),
            link: editor.isActive('link'),
            image: editor.isActive('image'),
            imageAlt: String(editor.getAttributes('image').alt ?? ''),
            canUndo: editor.can().undo(),
            canRedo: editor.can().redo(),
        }),
    });

    const openLink = () => {
        setLinkValue(String(editor.getAttributes('link').href ?? ''));
        setLinkError('');
        setLinkOpen(true);
    };

    const closeLink = () => {
        setLinkOpen(false);
        editor.chain().focus().run();
    };

    const applyLink = () => {
        if (linkValue.trim() === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            setLinkOpen(false);

            return;
        }

        if (editor.state.selection.empty && !editor.isActive('link')) {
            setLinkError('Select the text you want to link first.');

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: normaliseUrl(linkValue) })
            .run();
        setLinkOpen(false);
    };

    return (
        // Sticks under the form's action bar while a long text scrolls.
        <div className="sticky top-[4.75rem] z-20 rounded-t-md border-b bg-background">
            <div
                role="toolbar"
                aria-label="Text formatting"
                className="flex flex-wrap items-center gap-0.5 p-1"
            >
                <ToolbarButton
                    label="Bold"
                    active={state.bold}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                >
                    <Bold />
                </ToolbarButton>
                <ToolbarButton
                    label="Italic"
                    active={state.italic}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                >
                    <Italic />
                </ToolbarButton>
                <Divider />
                <ToolbarButton
                    label="Heading"
                    active={state.h2}
                    onClick={() =>
                        editor.chain().focus().toggleHeading({ level: 2 }).run()
                    }
                >
                    <Heading2 />
                </ToolbarButton>
                <ToolbarButton
                    label="Subheading"
                    active={state.h3}
                    onClick={() =>
                        editor.chain().focus().toggleHeading({ level: 3 }).run()
                    }
                >
                    <Heading3 />
                </ToolbarButton>
                <Divider />
                <ToolbarButton
                    label="Bulleted list"
                    active={state.bullets}
                    onClick={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                >
                    <List />
                </ToolbarButton>
                <ToolbarButton
                    label="Numbered list"
                    active={state.numbers}
                    onClick={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                >
                    <ListOrdered />
                </ToolbarButton>
                <ToolbarButton
                    label="Quote"
                    active={state.quote}
                    onClick={() =>
                        editor.chain().focus().toggleBlockquote().run()
                    }
                >
                    <Quote />
                </ToolbarButton>
                <Divider />
                <ToolbarButton
                    label="Link"
                    active={state.link || linkOpen}
                    onClick={openLink}
                >
                    <Link2 />
                </ToolbarButton>
                <ToolbarButton
                    label="Remove link"
                    disabled={!state.link}
                    onClick={() =>
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .unsetLink()
                            .run()
                    }
                >
                    <Unlink />
                </ToolbarButton>
                {allowImages && (
                    <>
                        <Divider />
                        <ToolbarButton
                            label="Insert image from the library"
                            active={state.image}
                            onClick={() => setPickerOpen(true)}
                        >
                            <ImagePlus />
                        </ToolbarButton>
                    </>
                )}
                <Divider />
                <ToolbarButton
                    label="Undo"
                    disabled={!state.canUndo}
                    onClick={() => editor.chain().focus().undo().run()}
                >
                    <Undo2 />
                </ToolbarButton>
                <ToolbarButton
                    label="Redo"
                    disabled={!state.canRedo}
                    onClick={() => editor.chain().focus().redo().run()}
                >
                    <Redo2 />
                </ToolbarButton>
            </div>

            {state.image && (
                // Not a <form>: this editor already lives inside the page's form.
                <div className="flex flex-wrap items-center gap-2 border-t bg-muted/40 p-2">
                    <span className="text-xs font-medium">Selected image</span>
                    <Input
                        type="text"
                        aria-label="Image description (alt text)"
                        placeholder="Describe the picture for people who cannot see it"
                        value={state.imageAlt}
                        maxLength={200}
                        className="min-w-48 flex-1 bg-background"
                        onChange={(event) =>
                            editor
                                .chain()
                                .updateAttributes('image', {
                                    alt: event.target.value,
                                })
                                .run()
                        }
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                editor.chain().focus().run();
                            }
                        }}
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            editor.chain().focus().deleteSelection().run()
                        }
                    >
                        <Trash2 /> Remove image
                    </Button>
                    {state.imageAlt.trim() === '' && (
                        <p className="w-full text-xs text-amber-700 dark:text-amber-400">
                            Add a description: screen readers and search engines
                            rely on it.
                        </p>
                    )}
                </div>
            )}

            {linkOpen && (
                <div className="flex flex-wrap items-center gap-2 border-t p-2">
                    <Input
                        autoFocus
                        type="text"
                        inputMode="url"
                        aria-label="Link address"
                        placeholder="https://example.com or name@example.com"
                        value={linkValue}
                        aria-invalid={linkError !== ''}
                        className="min-w-48 flex-1"
                        onChange={(event) => {
                            setLinkValue(event.target.value);
                            setLinkError('');
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                applyLink();
                            }

                            if (event.key === 'Escape') {
                                event.preventDefault();
                                closeLink();
                            }
                        }}
                    />
                    <Button type="button" size="sm" onClick={applyLink}>
                        Apply
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={closeLink}
                    >
                        Cancel
                    </Button>
                    {linkError && (
                        <p
                            role="alert"
                            className="w-full text-sm text-red-600 dark:text-red-400"
                        >
                            {linkError}
                        </p>
                    )}
                </div>
            )}

            {allowImages && (
                <MediaPicker
                    open={pickerOpen}
                    onOpenChange={setPickerOpen}
                    title="Insert an image"
                    confirmLabel="Insert image"
                    withAlt
                    onSelect={(item, alt) => {
                        editor
                            .chain()
                            .focus()
                            .setImage({
                                src: item.url,
                                alt: alt || item.alt || '',
                                width: item.width,
                                height: item.height,
                            })
                            .run();
                    }}
                />
            )}
        </div>
    );
}

const wordCount = (text: string): number =>
    text.trim() === '' ? 0 : text.trim().split(/\s+/).length;

/**
 * Rich-text field for story and event text. It only offers formatting the server's sanitiser
 * keeps (headings, bold, italic, lists, quotes, links, library images) and submits HTML through a
 * hidden input, so it works inside an ordinary Inertia <Form>.
 */
export default function RichTextEditor({
    id,
    name,
    label,
    defaultValue,
    invalid = false,
    describedBy,
    allowImages = true,
    minHeightClass = 'min-h-64',
}: Props) {
    const [html, setHtml] = useState(defaultValue);
    const [words, setWords] = useState(() =>
        wordCount(defaultValue.replace(/<[^>]*>/g, ' ')),
    );
    const hiddenInput = useInputNotifier(html);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                code: false,
                codeBlock: false,
                strike: false,
                underline: false,
                horizontalRule: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                    HTMLAttributes: { rel: 'noopener noreferrer' },
                },
            }),
            // Pictures come from the library only: pasted base64 data is ignored.
            ...(allowImages ? [Image.configure({ allowBase64: false })] : []),
        ],
        content: defaultValue,
        editorProps: {
            attributes: {
                id,
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': label,
                ...(describedBy ? { 'aria-describedby': describedBy } : {}),
                class: cn('rich-text px-3 py-2 outline-none', minHeightClass),
            },
        },
        onUpdate: ({ editor }) => {
            setHtml(editor.isEmpty ? '' : editor.getHTML());
            setWords(wordCount(editor.getText()));
        },
    });

    return (
        <div
            className={cn(
                'rounded-md border bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                invalid && 'border-destructive-foreground',
            )}
        >
            {editor && <Toolbar editor={editor} allowImages={allowImages} />}
            <EditorContent editor={editor} />
            <div className="flex justify-between gap-3 border-t px-3 py-1.5 text-xs text-muted-foreground">
                <span>
                    {words} {words === 1 ? 'word' : 'words'}
                </span>
                <span>
                    {words === 0
                        ? 'Nothing written yet'
                        : `About ${Math.max(1, Math.round(words / 200))} min read`}
                </span>
            </div>
            <input ref={hiddenInput} type="hidden" name={name} value={html} />
        </div>
    );
}
