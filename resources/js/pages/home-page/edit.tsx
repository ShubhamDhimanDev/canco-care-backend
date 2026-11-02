import { Form, Head } from '@inertiajs/react';
import HomePageController from '@/actions/App/Http/Controllers/Admin/HomePageController';
import AlertError from '@/components/alert-error';
import FormField from '@/components/form-field';
import Heading from '@/components/heading';
import ImagePicker from '@/components/media/image-picker';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import type { HomeContentForm, HomeDefaults } from '@/types';

type Props = {
    content: HomeContentForm;
    defaults: HomeDefaults;
    siteUrl: string;
};

export default function HomePageEdit({ content, defaults, siteUrl }: Props) {
    return (
        <>
            <Head title="Home page" />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Home page"
                        description="Change the words and pictures on the website's front page. Leave a field empty to use the original text."
                    />
                    {siteUrl && (
                        <a
                            href={siteUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="text-sm underline underline-offset-4"
                        >
                            View the home page
                        </a>
                    )}
                </div>

                <Form
                    {...HomePageController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {Object.keys(errors).length > 0 && (
                                <div role="alert">
                                    <AlertError
                                        errors={Object.values(errors)}
                                        title="The home page was not saved"
                                    />
                                </div>
                            )}

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Top banner
                                    </CardTitle>
                                    <CardDescription>
                                        The first thing visitors see. The heart
                                        replaces the “O” in “Hope”
                                        automatically.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-6 md:grid-cols-2">
                                    <div className="space-y-4">
                                        <FormField
                                            name="hero[title]"
                                            label="Headline"
                                            defaultValue={content.hero.title}
                                            placeholder={defaults.hero.title}
                                            maxLength={80}
                                            error={errors['hero.title']}
                                        />
                                        <FormField
                                            name="hero[text]"
                                            label="Supporting text"
                                            defaultValue={content.hero.text}
                                            placeholder={defaults.hero.text}
                                            rows={4}
                                            maxLength={400}
                                            error={errors['hero.text']}
                                        />
                                        <FormField
                                            name="hero[primary_label]"
                                            label="Donate button label"
                                            defaultValue={
                                                content.hero.primary_label
                                            }
                                            placeholder={
                                                defaults.hero.primary_label
                                            }
                                            help="This button opens the donation form."
                                            maxLength={30}
                                            error={errors['hero.primary_label']}
                                        />
                                        <FormField
                                            name="hero[secondary_label]"
                                            label="Second button label"
                                            defaultValue={
                                                content.hero.secondary_label
                                            }
                                            placeholder={
                                                defaults.hero.secondary_label
                                            }
                                            maxLength={30}
                                            error={
                                                errors['hero.secondary_label']
                                            }
                                        />
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
                                                errors['hero.secondary_href']
                                            }
                                        />
                                    </div>
                                    <ImagePicker
                                        fields={{
                                            mediaId: 'hero[image][media_id]',
                                            alt: 'hero[image][alt]',
                                            remove: 'hero[image][remove]',
                                        }}
                                        label="Banner picture"
                                        current={content.hero.image}
                                        altRequired
                                        error={errors['hero.image.media_id']}
                                        altError={errors['hero.image.alt']}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Introduction
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <FormField
                                        name="intro[eyebrow]"
                                        label="Small heading above the title"
                                        defaultValue={content.intro.eyebrow}
                                        placeholder={defaults.intro.eyebrow}
                                        maxLength={60}
                                        error={errors['intro.eyebrow']}
                                    />
                                    <FormField
                                        name="intro[title]"
                                        label="Title"
                                        defaultValue={content.intro.title}
                                        placeholder={defaults.intro.title}
                                        maxLength={60}
                                        error={errors['intro.title']}
                                    />
                                    <FormField
                                        name="intro[text]"
                                        label="Text"
                                        defaultValue={content.intro.text}
                                        placeholder={defaults.intro.text}
                                        rows={4}
                                        maxLength={600}
                                        error={errors['intro.text']}
                                    />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <FormField
                                            name="intro[button_label]"
                                            label="Button label"
                                            defaultValue={
                                                content.intro.button_label
                                            }
                                            placeholder={
                                                defaults.intro.button_label
                                            }
                                            maxLength={30}
                                            error={errors['intro.button_label']}
                                        />
                                        <FormField
                                            name="intro[button_href]"
                                            label="Button link"
                                            defaultValue={
                                                content.intro.button_href
                                            }
                                            placeholder={
                                                defaults.intro.button_href
                                            }
                                            maxLength={300}
                                            error={errors['intro.button_href']}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {content.cards.map((card, index) => (
                                <Card key={index}>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Card {index + 1}
                                        </CardTitle>
                                        <CardDescription>
                                            One of the two white cards below the
                                            introduction.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-6 md:grid-cols-2">
                                        <div className="space-y-4">
                                            <FormField
                                                name={`cards[${index}][eyebrow]`}
                                                label="Small heading"
                                                defaultValue={card.eyebrow}
                                                placeholder={
                                                    defaults.cards[index]
                                                        ?.eyebrow
                                                }
                                                maxLength={60}
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
                                                    defaults.cards[index]?.title
                                                }
                                                maxLength={40}
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
                                                    defaults.cards[index]?.text
                                                }
                                                rows={4}
                                                maxLength={400}
                                                error={
                                                    errors[
                                                        `cards.${index}.text`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <ImagePicker
                                            fields={{
                                                mediaId: `cards[${index}][image][media_id]`,
                                                alt: `cards[${index}][image][alt]`,
                                                remove: `cards[${index}][image][remove]`,
                                            }}
                                            label="Card picture"
                                            current={card.image}
                                            altRequired
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
                                    </CardContent>
                                </Card>
                            ))}

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Latest stories
                                    </CardTitle>
                                    <CardDescription>
                                        The stories shown here are chosen on
                                        each story (“Feature on the home page”);
                                        the newest fill any gaps. The impact
                                        figure is edited in Settings.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        name="stories[eyebrow]"
                                        label="Small heading"
                                        defaultValue={content.stories.eyebrow}
                                        placeholder={defaults.stories.eyebrow}
                                        maxLength={60}
                                        error={errors['stories.eyebrow']}
                                    />
                                    <FormField
                                        name="stories[title]"
                                        label="Title"
                                        defaultValue={content.stories.title}
                                        placeholder={defaults.stories.title}
                                        maxLength={60}
                                        error={errors['stories.title']}
                                    />
                                </CardContent>
                            </Card>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />} Save home page
                                </Button>
                            </div>
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
