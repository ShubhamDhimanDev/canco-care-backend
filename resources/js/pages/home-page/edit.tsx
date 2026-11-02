import { Form, Head } from '@inertiajs/react';
import { Images, LayoutPanelTop, Newspaper, Sparkles } from 'lucide-react';
import { useState } from 'react';
import HomePageController from '@/actions/App/Http/Controllers/Admin/HomePageController';
import AlertError from '@/components/alert-error';
import FormActionBar, { BarLink } from '@/components/form-action-bar';
import FormField from '@/components/form-field';
import {
    BUNDLED_IMAGES,
    CardPreview,
    HeroPreview,
    IntroPreview,
    PreviewFrame,
    StoriesPreview,
} from '@/components/home/section-previews';
import ImagePicker from '@/components/media/image-picker';
import SectionCard from '@/components/section-card';
import { useFormDirty } from '@/hooks/use-form-dirty';
import type { HomeContentForm, HomeDefaults } from '@/types';

type Props = {
    content: HomeContentForm;
    defaults: HomeDefaults;
    siteUrl: string;
};

/** What the form holds as text; images are tracked separately by their address. */
type Draft = {
    hero: Omit<HomeContentForm['hero'], 'image'>;
    intro: HomeContentForm['intro'];
    cards: Omit<HomeContentForm['cards'][number], 'image'>[];
    stories: HomeContentForm['stories'];
};

/** An empty field falls back to the original text, exactly like the website does. */
const orDefault = (value: string, fallback: string): string =>
    value.trim() === '' ? fallback : value;

const SECTIONS = [
    { id: 'banner', label: 'Top banner' },
    { id: 'intro', label: 'Introduction' },
    { id: 'card-1', label: 'Card 1' },
    { id: 'card-2', label: 'Card 2' },
    { id: 'stories', label: 'Latest stories' },
];

/** The two-column body of a section: the fields, and its own live preview beside them. */
function Split({
    fields,
    preview,
}: {
    fields: React.ReactNode;
    preview: React.ReactNode;
}) {
    return (
        <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <div className="space-y-4">{fields}</div>
            <div className="xl:sticky xl:top-24">{preview}</div>
        </div>
    );
}

export default function HomePageEdit({ content, defaults, siteUrl }: Props) {
    const { dirty, clear, bindings } = useFormDirty();
    const [draft, setDraft] = useState<Draft>(() => {
        const { image: heroImage, ...hero } = content.hero;
        void heroImage;

        return {
            hero,
            intro: content.intro,
            cards: content.cards.map(({ image, ...card }) => {
                void image;

                return card;
            }),
            stories: content.stories,
        };
    });
    const [heroImage, setHeroImage] = useState<string | null>(null);
    const [cardImages, setCardImages] = useState<(string | null)[]>(
        content.cards.map(() => null),
    );

    const setHero = (key: keyof Draft['hero']) => (value: string) =>
        setDraft((d) => ({ ...d, hero: { ...d.hero, [key]: value } }));
    const setIntro = (key: keyof Draft['intro']) => (value: string) =>
        setDraft((d) => ({ ...d, intro: { ...d.intro, [key]: value } }));
    const setStories = (key: keyof Draft['stories']) => (value: string) =>
        setDraft((d) => ({ ...d, stories: { ...d.stories, [key]: value } }));
    const setCard =
        (index: number, key: keyof Draft['cards'][number]) => (value: string) =>
            setDraft((d) => ({
                ...d,
                cards: d.cards.map((card, i) =>
                    i === index ? { ...card, [key]: value } : card,
                ),
            }));

    const hero = draft.hero;
    const intro = draft.intro;

    return (
        <>
            <Head title="Home page" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <Form
                    {...HomePageController.update.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={clear}
                    className="space-y-6"
                    {...bindings}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <FormActionBar
                                title="Home page"
                                subtitle="Leave a field empty to use the original text."
                                extra={
                                    siteUrl ? (
                                        <BarLink href={siteUrl}>
                                            View live page
                                        </BarLink>
                                    ) : null
                                }
                                submitLabel="Save home page"
                                processing={processing}
                                dirty={dirty}
                                saved={recentlySuccessful}
                            />

                            <nav
                                aria-label="Sections of the home page"
                                className="flex flex-wrap gap-2"
                            >
                                {SECTIONS.map((section) => (
                                    <a
                                        key={section.id}
                                        href={`#${section.id}`}
                                        className="rounded-full border px-3 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        {section.label}
                                    </a>
                                ))}
                            </nav>

                            {Object.keys(errors).length > 0 && (
                                <div role="alert">
                                    <AlertError
                                        errors={Object.values(errors)}
                                        title="The home page was not saved"
                                    />
                                </div>
                            )}

                            <SectionCard
                                id="banner"
                                icon={LayoutPanelTop}
                                title="Top banner"
                                description="The first thing visitors see. The heart replaces the “O” in “Hope” automatically."
                            >
                                <Split
                                    fields={
                                        <>
                                            <FormField
                                                name="hero[title]"
                                                label="Headline"
                                                defaultValue={
                                                    content.hero.title
                                                }
                                                placeholder={
                                                    defaults.hero.title
                                                }
                                                maxLength={80}
                                                counter
                                                onValueChange={setHero('title')}
                                                error={errors['hero.title']}
                                            />
                                            <FormField
                                                name="hero[text]"
                                                label="Supporting text"
                                                defaultValue={content.hero.text}
                                                placeholder={defaults.hero.text}
                                                rows={4}
                                                maxLength={400}
                                                counter
                                                onValueChange={setHero('text')}
                                                error={errors['hero.text']}
                                            />
                                            <div className="grid items-start gap-4 sm:grid-cols-2">
                                                <FormField
                                                    name="hero[primary_label]"
                                                    label="Donate button label"
                                                    defaultValue={
                                                        content.hero
                                                            .primary_label
                                                    }
                                                    placeholder={
                                                        defaults.hero
                                                            .primary_label
                                                    }
                                                    help="Opens the donation form."
                                                    maxLength={30}
                                                    onValueChange={setHero(
                                                        'primary_label',
                                                    )}
                                                    error={
                                                        errors[
                                                            'hero.primary_label'
                                                        ]
                                                    }
                                                />
                                                <FormField
                                                    name="hero[secondary_label]"
                                                    label="Second button label"
                                                    defaultValue={
                                                        content.hero
                                                            .secondary_label
                                                    }
                                                    placeholder={
                                                        defaults.hero
                                                            .secondary_label
                                                    }
                                                    maxLength={30}
                                                    onValueChange={setHero(
                                                        'secondary_label',
                                                    )}
                                                    error={
                                                        errors[
                                                            'hero.secondary_label'
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <FormField
                                                name="hero[secondary_href]"
                                                label="Second button link"
                                                defaultValue={
                                                    content.hero.secondary_href
                                                }
                                                placeholder={
                                                    defaults.hero.secondary_href
                                                }
                                                help="A page on this site (like /partner-with-us) or a full address."
                                                maxLength={300}
                                                error={
                                                    errors[
                                                        'hero.secondary_href'
                                                    ]
                                                }
                                            />
                                            <ImagePicker
                                                fields={{
                                                    mediaId:
                                                        'hero[image][media_id]',
                                                    alt: 'hero[image][alt]',
                                                    remove: 'hero[image][remove]',
                                                }}
                                                label="Banner picture"
                                                current={content.hero.image}
                                                altRequired
                                                onImageChange={setHeroImage}
                                                error={
                                                    errors[
                                                        'hero.image.media_id'
                                                    ]
                                                }
                                                altError={
                                                    errors['hero.image.alt']
                                                }
                                            />
                                        </>
                                    }
                                    preview={
                                        <PreviewFrame label="Live preview · top banner">
                                            {(mobile) => (
                                                <HeroPreview
                                                    mobile={mobile}
                                                    title={orDefault(
                                                        hero.title,
                                                        defaults.hero.title,
                                                    )}
                                                    text={orDefault(
                                                        hero.text,
                                                        defaults.hero.text,
                                                    )}
                                                    primaryLabel={orDefault(
                                                        hero.primary_label,
                                                        defaults.hero
                                                            .primary_label,
                                                    )}
                                                    secondaryLabel={orDefault(
                                                        hero.secondary_label,
                                                        defaults.hero
                                                            .secondary_label,
                                                    )}
                                                    imageUrl={heroImage}
                                                />
                                            )}
                                        </PreviewFrame>
                                    }
                                />
                            </SectionCard>

                            <SectionCard
                                id="intro"
                                icon={Sparkles}
                                title="Introduction"
                                description="The big title and paragraph under the banner."
                            >
                                <Split
                                    fields={
                                        <>
                                            <FormField
                                                name="intro[eyebrow]"
                                                label="Small heading above the title"
                                                defaultValue={
                                                    content.intro.eyebrow
                                                }
                                                placeholder={
                                                    defaults.intro.eyebrow
                                                }
                                                maxLength={60}
                                                counter
                                                onValueChange={setIntro(
                                                    'eyebrow',
                                                )}
                                                error={errors['intro.eyebrow']}
                                            />
                                            <FormField
                                                name="intro[title]"
                                                label="Title"
                                                defaultValue={
                                                    content.intro.title
                                                }
                                                placeholder={
                                                    defaults.intro.title
                                                }
                                                maxLength={60}
                                                counter
                                                onValueChange={setIntro(
                                                    'title',
                                                )}
                                                error={errors['intro.title']}
                                            />
                                            <FormField
                                                name="intro[text]"
                                                label="Text"
                                                defaultValue={
                                                    content.intro.text
                                                }
                                                placeholder={
                                                    defaults.intro.text
                                                }
                                                rows={4}
                                                maxLength={600}
                                                counter
                                                onValueChange={setIntro('text')}
                                                error={errors['intro.text']}
                                            />
                                            <div className="grid items-start gap-4 sm:grid-cols-2">
                                                <FormField
                                                    name="intro[button_label]"
                                                    label="Button label"
                                                    defaultValue={
                                                        content.intro
                                                            .button_label
                                                    }
                                                    placeholder={
                                                        defaults.intro
                                                            .button_label
                                                    }
                                                    maxLength={30}
                                                    onValueChange={setIntro(
                                                        'button_label',
                                                    )}
                                                    error={
                                                        errors[
                                                            'intro.button_label'
                                                        ]
                                                    }
                                                />
                                                <FormField
                                                    name="intro[button_href]"
                                                    label="Button link"
                                                    defaultValue={
                                                        content.intro
                                                            .button_href
                                                    }
                                                    placeholder={
                                                        defaults.intro
                                                            .button_href
                                                    }
                                                    maxLength={300}
                                                    error={
                                                        errors[
                                                            'intro.button_href'
                                                        ]
                                                    }
                                                />
                                            </div>
                                        </>
                                    }
                                    preview={
                                        <PreviewFrame label="Live preview · introduction">
                                            {(mobile) => (
                                                <IntroPreview
                                                    mobile={mobile}
                                                    eyebrow={orDefault(
                                                        intro.eyebrow,
                                                        defaults.intro.eyebrow,
                                                    )}
                                                    title={orDefault(
                                                        intro.title,
                                                        defaults.intro.title,
                                                    )}
                                                    text={orDefault(
                                                        intro.text,
                                                        defaults.intro.text,
                                                    )}
                                                    buttonLabel={orDefault(
                                                        intro.button_label,
                                                        defaults.intro
                                                            .button_label,
                                                    )}
                                                />
                                            )}
                                        </PreviewFrame>
                                    }
                                />
                            </SectionCard>

                            {content.cards.map((card, index) => (
                                <SectionCard
                                    key={index}
                                    id={`card-${index + 1}`}
                                    icon={Images}
                                    title={`Card ${index + 1}`}
                                    description="One of the two white cards below the introduction."
                                >
                                    <Split
                                        fields={
                                            <>
                                                <FormField
                                                    name={`cards[${index}][eyebrow]`}
                                                    label="Small heading"
                                                    defaultValue={card.eyebrow}
                                                    placeholder={
                                                        defaults.cards[index]
                                                            ?.eyebrow
                                                    }
                                                    maxLength={60}
                                                    counter
                                                    onValueChange={setCard(
                                                        index,
                                                        'eyebrow',
                                                    )}
                                                    error={
                                                        errors[
                                                            `cards.${index}.eyebrow`
                                                        ]
                                                    }
                                                />
                                                <FormField
                                                    name={`cards[${index}][title]`}
                                                    label="Title"
                                                    defaultValue={card.title}
                                                    placeholder={
                                                        defaults.cards[index]
                                                            ?.title
                                                    }
                                                    maxLength={40}
                                                    counter
                                                    onValueChange={setCard(
                                                        index,
                                                        'title',
                                                    )}
                                                    error={
                                                        errors[
                                                            `cards.${index}.title`
                                                        ]
                                                    }
                                                />
                                                <FormField
                                                    name={`cards[${index}][text]`}
                                                    label="Text"
                                                    defaultValue={card.text}
                                                    placeholder={
                                                        defaults.cards[index]
                                                            ?.text
                                                    }
                                                    rows={4}
                                                    maxLength={400}
                                                    counter
                                                    onValueChange={setCard(
                                                        index,
                                                        'text',
                                                    )}
                                                    error={
                                                        errors[
                                                            `cards.${index}.text`
                                                        ]
                                                    }
                                                />
                                                <ImagePicker
                                                    fields={{
                                                        mediaId: `cards[${index}][image][media_id]`,
                                                        alt: `cards[${index}][image][alt]`,
                                                        remove: `cards[${index}][image][remove]`,
                                                    }}
                                                    label="Card picture"
                                                    current={card.image}
                                                    altRequired
                                                    onImageChange={(url) =>
                                                        setCardImages(
                                                            (images) =>
                                                                images.map(
                                                                    (
                                                                        image,
                                                                        i,
                                                                    ) =>
                                                                        i ===
                                                                        index
                                                                            ? url
                                                                            : image,
                                                                ),
                                                        )
                                                    }
                                                    error={
                                                        errors[
                                                            `cards.${index}.image.media_id`
                                                        ]
                                                    }
                                                    altError={
                                                        errors[
                                                            `cards.${index}.image.alt`
                                                        ]
                                                    }
                                                />
                                            </>
                                        }
                                        preview={
                                            <PreviewFrame
                                                label={`Live preview · card ${index + 1}`}
                                            >
                                                {(mobile) => (
                                                    <CardPreview
                                                        mobile={mobile}
                                                        eyebrow={orDefault(
                                                            draft.cards[index]
                                                                .eyebrow,
                                                            defaults.cards[
                                                                index
                                                            ]?.eyebrow ?? '',
                                                        )}
                                                        title={orDefault(
                                                            draft.cards[index]
                                                                .title,
                                                            defaults.cards[
                                                                index
                                                            ]?.title ?? '',
                                                        )}
                                                        text={orDefault(
                                                            draft.cards[index]
                                                                .text,
                                                            defaults.cards[
                                                                index
                                                            ]?.text ?? '',
                                                        )}
                                                        imageUrl={
                                                            cardImages[index]
                                                        }
                                                        fallbackImage={
                                                            BUNDLED_IMAGES
                                                                .cards[index] ??
                                                            BUNDLED_IMAGES
                                                                .cards[0]
                                                        }
                                                    />
                                                )}
                                            </PreviewFrame>
                                        }
                                    />
                                </SectionCard>
                            ))}

                            <SectionCard
                                id="stories"
                                icon={Newspaper}
                                title="Latest stories"
                                description="The stories shown here are chosen on each story (“Feature on the home page”); the newest fill any gaps. The impact figure is edited in Settings."
                            >
                                <Split
                                    fields={
                                        <>
                                            <FormField
                                                name="stories[eyebrow]"
                                                label="Small heading"
                                                defaultValue={
                                                    content.stories.eyebrow
                                                }
                                                placeholder={
                                                    defaults.stories.eyebrow
                                                }
                                                maxLength={60}
                                                counter
                                                onValueChange={setStories(
                                                    'eyebrow',
                                                )}
                                                error={
                                                    errors['stories.eyebrow']
                                                }
                                            />
                                            <FormField
                                                name="stories[title]"
                                                label="Title"
                                                defaultValue={
                                                    content.stories.title
                                                }
                                                placeholder={
                                                    defaults.stories.title
                                                }
                                                maxLength={60}
                                                counter
                                                onValueChange={setStories(
                                                    'title',
                                                )}
                                                error={errors['stories.title']}
                                            />
                                        </>
                                    }
                                    preview={
                                        <PreviewFrame label="Live preview · latest stories">
                                            {(mobile) => (
                                                <StoriesPreview
                                                    mobile={mobile}
                                                    eyebrow={orDefault(
                                                        draft.stories.eyebrow,
                                                        defaults.stories
                                                            .eyebrow,
                                                    )}
                                                    title={orDefault(
                                                        draft.stories.title,
                                                        defaults.stories.title,
                                                    )}
                                                />
                                            )}
                                        </PreviewFrame>
                                    }
                                />
                            </SectionCard>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

HomePageEdit.layout = {
    breadcrumbs: [{ title: 'Home page', href: HomePageController.edit() }],
};
