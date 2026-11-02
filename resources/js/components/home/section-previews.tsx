import { Monitor, Smartphone } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** The pictures that ship with the website; used until an editor chooses one (kept in public/images/home). */
export const BUNDLED_IMAGES = {
    hero: '/images/home/homeBannerImg.webp',
    cards: ['/images/home/projects.webp', '/images/home/partnership.webp'],
    heart: '/images/home/bigHeartIcon.webp',
} as const;

const DESKTOP_WIDTH = 1200;
const MOBILE_WIDTH = 390;

const NAVY = '#1d376b';
const SLATE = '#666c91';
const ORANGE = '#f15024';
const INK = '#484848';

/**
 * A scaled-down copy of how a part of the page will look, with a desktop / phone switch. The preview
 * is drawn at the website's real width and shrunk to fit, so line breaks match what visitors see
 * (fonts differ slightly: the website uses Montserrat).
 */
export function PreviewFrame({
    label,
    children,
}: {
    label: string;
    children: (mobile: boolean) => ReactNode;
}) {
    const [mobile, setMobile] = useState(false);
    const width = mobile ? MOBILE_WIDTH : DESKTOP_WIDTH;
    const outer = useRef<HTMLDivElement>(null);
    const inner = useRef<HTMLDivElement>(null);
    const [box, setBox] = useState({ available: 0, content: 0 });

    useLayoutEffect(() => {
        const measure = () =>
            setBox({
                available: outer.current?.clientWidth ?? 0,
                content: inner.current?.offsetHeight ?? 0,
            });

        measure();

        const observer = new ResizeObserver(measure);

        if (outer.current) {
            observer.observe(outer.current);
        }

        if (inner.current) {
            observer.observe(inner.current);
        }

        return () => observer.disconnect();
    }, [width]);

    const scale = box.available > 0 ? Math.min(1, box.available / width) : 1;
    const offset = Math.max(0, (box.available - width * scale) / 2);

    return (
        <figure className="m-0 space-y-2">
            <figcaption className="flex items-center justify-between gap-2">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span
                    role="group"
                    aria-label="Preview size"
                    className="inline-flex rounded-md bg-muted p-0.5"
                >
                    {[
                        { value: false, text: 'Desktop', Icon: Monitor },
                        { value: true, text: 'Phone', Icon: Smartphone },
                    ].map(({ value, text, Icon }) => (
                        <button
                            key={text}
                            type="button"
                            aria-pressed={mobile === value}
                            onClick={() => setMobile(value)}
                            className={cn(
                                'inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium transition-colors',
                                mobile === value
                                    ? 'bg-background text-foreground shadow-sm dark:bg-neutral-600'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-3.5" aria-hidden="true" />
                            {text}
                        </button>
                    ))}
                </span>
            </figcaption>

            {/* Decorative copy of the form values: hidden from assistive technology. */}
            <div
                ref={outer}
                aria-hidden="true"
                className="overflow-hidden rounded-lg border bg-[#f5f6fa]"
                style={{ height: box.content * scale || undefined }}
            >
                <div
                    ref={inner}
                    style={{
                        width,
                        transform: `scale(${scale})`,
                        transformOrigin: 'top left',
                        marginLeft: offset,
                        fontFamily:
                            'Montserrat, ui-sans-serif, system-ui, sans-serif',
                    }}
                >
                    {children(mobile)}
                </div>
            </div>
        </figure>
    );
}

function Img({
    src,
    className,
    style,
}: {
    src: string;
    className?: string;
    style?: React.CSSProperties;
}) {
    return (
        <img
            src={src}
            alt=""
            className={className}
            style={style}
            onError={(event) => {
                event.currentTarget.style.visibility = 'hidden';
            }}
        />
    );
}

/** An orange (call to action) or slate button, as drawn on the website. */
function Btn({
    color,
    children,
    small = false,
}: {
    color: 'orange' | 'slate';
    children: ReactNode;
    small?: boolean;
}) {
    return (
        <span
            className="inline-flex items-center justify-center rounded-xl text-white uppercase"
            style={{
                background: color === 'orange' ? ORANGE : SLATE,
                padding: small ? '10px 18px' : '13px 23px',
                fontSize: 16,
                fontWeight: 500,
                letterSpacing: 1.6,
                border: '2px solid transparent',
            }}
        >
            {children}
        </span>
    );
}

/** Uppercases a headline and puts the heart where the "O" of "HOPE" would be. */
function HeartTitle({ title }: { title: string }) {
    const upper = title.toUpperCase();
    const at = upper.indexOf('HOPE');

    if (at === -1) {
        return <>{upper}</>;
    }

    return (
        <>
            {upper.slice(0, at)}H
            <img
                src={BUNDLED_IMAGES.heart}
                alt=""
                style={{
                    display: 'inline-block',
                    height: '0.95em',
                    width: 'auto',
                    margin: '0 0.04em',
                    verticalAlign: '-0.12em',
                }}
            />
            {upper.slice(at + 2)}
        </>
    );
}

export function HeroPreview({
    title,
    text,
    primaryLabel,
    secondaryLabel,
    imageUrl,
    mobile,
}: {
    title: string;
    text: string;
    primaryLabel: string;
    secondaryLabel: string;
    imageUrl: string | null;
    mobile: boolean;
}) {
    return (
        <div style={{ padding: mobile ? '40px 24px 40px' : '56px 48px' }}>
            <div
                style={{
                    display: 'flex',
                    flexDirection: mobile ? 'column-reverse' : 'row',
                    alignItems: 'center',
                    gap: mobile ? 28 : 24,
                }}
            >
                <div
                    style={{
                        flex: mobile ? undefined : '0 0 42%',
                        textAlign: mobile ? 'center' : 'left',
                    }}
                >
                    <h3
                        style={{
                            margin: 0,
                            color: NAVY,
                            fontWeight: 700,
                            letterSpacing: 4.5,
                            fontSize: mobile ? 28 : 32,
                            lineHeight: mobile ? '36px' : '44px',
                        }}
                    >
                        <HeartTitle title={title} />
                    </h3>
                    <p
                        style={{
                            color: INK,
                            fontSize: 18,
                            lineHeight: '32px',
                            fontWeight: 500,
                            margin: '28px 0',
                        }}
                    >
                        {text}
                    </p>
                    <div
                        style={{
                            display: 'flex',
                            gap: 20,
                            flexDirection: mobile ? 'column' : 'row',
                            alignItems: mobile ? 'center' : 'flex-start',
                        }}
                    >
                        <Btn color="orange">{primaryLabel}</Btn>
                        <Btn color="slate">{secondaryLabel}</Btn>
                    </div>
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <Img
                        src={imageUrl ?? BUNDLED_IMAGES.hero}
                        style={{
                            display: 'block',
                            width: '100%',
                            maxWidth: 596,
                            height: 'auto',
                            marginLeft: 'auto',
                            marginRight: mobile ? 'auto' : 0,
                        }}
                    />
                </div>
            </div>
        </div>
    );
}

export function IntroPreview({
    eyebrow,
    title,
    text,
    buttonLabel,
    mobile,
}: {
    eyebrow: string;
    title: string;
    text: string;
    buttonLabel: string;
    mobile: boolean;
}) {
    return (
        <div
            style={{
                padding: mobile ? '40px 24px' : '56px 96px',
                textAlign: 'center',
            }}
        >
            <p
                style={{
                    margin: '0 0 8px',
                    color: SLATE,
                    fontWeight: 600,
                    fontSize: mobile ? 20 : 25,
                    lineHeight: '32px',
                }}
            >
                {eyebrow}
            </p>
            <h3
                style={{
                    margin: 0,
                    color: NAVY,
                    fontWeight: 700,
                    letterSpacing: mobile ? 2.76 : 4.5,
                    fontSize: mobile ? 46 : 72,
                    lineHeight: mobile ? '58px' : '82px',
                }}
            >
                {title}
            </h3>
            <p
                style={{
                    margin: '20px auto 36px',
                    maxWidth: 880,
                    color: INK,
                    fontSize: 18,
                    lineHeight: '32px',
                    fontWeight: 500,
                }}
            >
                {text}
            </p>
            <Btn color="slate">{buttonLabel}</Btn>
        </div>
    );
}

export function CardPreview({
    eyebrow,
    title,
    text,
    imageUrl,
    fallbackImage,
    mobile,
}: {
    eyebrow: string;
    title: string;
    text: string;
    imageUrl: string | null;
    fallbackImage: string;
    mobile: boolean;
}) {
    return (
        <div
            style={{
                padding: mobile ? '170px 24px 40px' : '170px 330px 56px',
            }}
        >
            <div
                style={{
                    background: '#fff',
                    borderRadius: 16,
                    boxShadow: '5px 5px 30px 0 rgb(29 55 107 / 0.1)',
                    padding: mobile ? '0 28px 40px' : '0 50px 50px',
                    textAlign: 'center',
                }}
            >
                <Img
                    src={imageUrl ?? fallbackImage}
                    style={{
                        display: 'block',
                        width: 270,
                        height: 'auto',
                        margin: '-148px auto 40px',
                    }}
                />
                <p
                    style={{
                        margin: '0 0 8px',
                        color: SLATE,
                        fontWeight: 600,
                        fontSize: 25,
                        lineHeight: '32px',
                    }}
                >
                    {eyebrow}
                </p>
                <h3
                    style={{
                        margin: 0,
                        color: NAVY,
                        fontWeight: 700,
                        letterSpacing: 1.8,
                        fontSize: mobile || title.length > 10 ? 35 : 54,
                        lineHeight:
                            mobile || title.length > 10 ? '42px' : '64px',
                    }}
                >
                    {title}
                </h3>
                <p
                    style={{
                        margin: '8px 0 0',
                        color: INK,
                        fontSize: 18,
                        lineHeight: '32px',
                        fontWeight: 500,
                    }}
                >
                    {text}
                </p>
            </div>
        </div>
    );
}

export function StoriesPreview({
    eyebrow,
    title,
    mobile,
}: {
    eyebrow: string;
    title: string;
    mobile: boolean;
}) {
    return (
        <div style={{ padding: mobile ? '40px 24px' : '56px 48px' }}>
            <div style={{ textAlign: 'center', marginBottom: 90 }}>
                <p
                    style={{
                        margin: '0 0 8px',
                        color: SLATE,
                        fontWeight: 600,
                        fontSize: mobile ? 20 : 25,
                        lineHeight: '32px',
                    }}
                >
                    {eyebrow}
                </p>
                <h3
                    style={{
                        margin: 0,
                        color: NAVY,
                        fontWeight: 700,
                        letterSpacing: mobile ? 1.92 : 4.5,
                        fontSize: mobile ? 32 : 54,
                        lineHeight: mobile ? '38px' : '64.8px',
                    }}
                >
                    {title}
                </h3>
            </div>
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: mobile ? '1fr' : 'repeat(3, 1fr)',
                    gap: mobile ? 90 : 24,
                }}
            >
                {(mobile ? [0] : [0, 1, 2]).map((card) => (
                    <div
                        key={card}
                        style={{
                            background: '#fff',
                            borderRadius: 16,
                            boxShadow: '5px 5px 30px 0 rgb(29 55 107 / 0.1)',
                            padding: '0 32px 50px',
                        }}
                    >
                        <div
                            style={{
                                height: 197,
                                marginTop: -50,
                                borderRadius: 16,
                                background:
                                    'linear-gradient(135deg,#dfe3ee,#c3c9dc)',
                            }}
                        />
                        <div
                            style={{
                                margin: '20px auto 12px',
                                height: 18,
                                width: '70%',
                                borderRadius: 4,
                                background: '#d4d8e6',
                            }}
                        />
                        <div
                            style={{
                                margin: '0 auto 6px',
                                height: 10,
                                width: '90%',
                                borderRadius: 4,
                                background: '#e6e9f1',
                            }}
                        />
                        <div
                            style={{
                                margin: '0 auto',
                                height: 10,
                                width: '75%',
                                borderRadius: 4,
                                background: '#e6e9f1',
                            }}
                        />
                    </div>
                ))}
            </div>
            <p
                style={{
                    margin: '56px 0 0',
                    textAlign: 'center',
                    color: SLATE,
                    fontSize: 14,
                }}
            >
                The story cards are filled in from your stories.
            </p>
        </div>
    );
}
