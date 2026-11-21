<?php

namespace App\Support;

use App\Models\Media;
use App\Services\Settings;

/**
 * The editable pages of the website other than the home page (docs/plan/14 #1, #3): what each page lets
 * an editor change, and the text it shows until they do.
 *
 * Like the home page (`HomeContent`), the stored document holds only what an editor changed; the
 * defaults reproduce the text of cancocares.ca, so an empty database renders today's site, and
 * clearing a field restores its default instead of leaving a hole in the page. The layouts stay
 * fixed (they are pixel-matched to the live design): editors change words and pictures, not structure.
 *
 * Field types: `text` (one line), `textarea` (plain paragraph), `rich` (formatted text, sanitised on
 * save) and `image` (a media-library picture; none chosen = the picture bundled with the website).
 * A string `{amount}` in any text is replaced by the impact counter's figure in the public API.
 */
final class PageCatalog
{
    /** The only keys an image value may have. */
    private const IMAGE = ['media_id' => null, 'alt' => null];

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::pages());
    }

    public static function exists(string $slug): bool
    {
        return array_key_exists($slug, self::pages());
    }

    /**
     * What the admin form draws: sections of fields, with each field's default.
     *
     * @return array{label: string, path: string, description: string, sections: list<array<string, mixed>>}
     */
    public static function definition(string $slug): array
    {
        return self::pages()[$slug] ?? throw new \InvalidArgumentException("Unknown page [{$slug}].");
    }

    /**
     * @return array<string, array<string, mixed>> key => field definition
     */
    public static function fields(string $slug): array
    {
        $fields = [];

        foreach (self::definition($slug)['sections'] as $section) {
            $fields += $section['fields'];
        }

        return $fields;
    }

    /**
     * @return array<string, mixed> key => default value (an image's default is the empty image)
     */
    public static function defaults(string $slug): array
    {
        return array_map(
            fn (array $field): mixed => $field['type'] === 'image' ? self::IMAGE : $field['default'],
            self::fields($slug),
        );
    }

    /**
     * Defaults overlaid with the stored document; blank text falls back to the default.
     *
     * @return array<string, mixed>
     */
    public static function merged(string $slug, Settings $settings): array
    {
        $stored = $settings->get("pages.{$slug}");
        $stored = is_array($stored) ? $stored : [];
        $content = self::defaults($slug);

        foreach ($content as $key => $default) {
            $value = $stored[$key] ?? null;

            if (is_array($default)) {
                $content[$key] = is_array($value) ? array_intersect_key($value + self::IMAGE, self::IMAGE) : $default;
            } elseif (is_string($value) && trim($value) !== '') {
                $content[$key] = $value;
            }
        }

        return $content;
    }

    /**
     * The document the public API serves: images resolved to `{url, alt, width, height}` (or null).
     *
     * @return array<string, mixed>
     */
    public static function forApi(string $slug, Settings $settings): array
    {
        $content = self::merged($slug, $settings);
        $ids = array_values(array_filter(array_map(
            fn (mixed $value): int => is_array($value) ? (int) ($value['media_id'] ?? 0) : 0,
            $content,
        )));
        $media = $ids === [] ? collect() : Media::query()->whereKey($ids)->get()->keyBy('id');
        $amount = (new ImpactFigure($settings))->amountText();

        foreach ($content as $key => $value) {
            if (is_array($value)) {
                $item = $media->get($value['media_id'] ?? 0);
                $content[$key] = $item instanceof Media ? $item->asImage($value['alt'] ?? null) : null;
            } else {
                $content[$key] = str_replace('{amount}', $amount, (string) $value);
            }
        }

        return $content;
    }

    /**
     * Every library id any page refers to (for "where is this picture used?").
     *
     * @return array<string, list<int>> slug => media ids
     */
    public static function mediaIdsBySlug(Settings $settings): array
    {
        $out = [];

        foreach (self::slugs() as $slug) {
            $stored = $settings->get("pages.{$slug}");
            $ids = [];

            foreach (is_array($stored) ? $stored : [] as $value) {
                if (is_array($value) && filled($value['media_id'] ?? null)) {
                    $ids[] = (int) $value['media_id'];
                }
            }

            $out[$slug] = $ids;
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private static function text(string $label, string $default, int $max = 120, ?string $help = null): array
    {
        return ['type' => 'text', 'label' => $label, 'default' => $default, 'max' => $max, 'help' => $help];
    }

    /**
     * @return array<string, mixed>
     */
    private static function textarea(string $label, string $default, int $max = 800, ?string $help = null): array
    {
        return ['type' => 'textarea', 'label' => $label, 'default' => $default, 'max' => $max, 'help' => $help];
    }

    /**
     * @return array<string, mixed>
     */
    private static function rich(string $label, string $default, int $max = 20000, ?string $help = null): array
    {
        return ['type' => 'rich', 'label' => $label, 'default' => $default, 'max' => $max, 'help' => $help];
    }

    /**
     * @return array<string, mixed>
     */
    private static function image(string $label, ?string $help = null): array
    {
        return ['type' => 'image', 'label' => $label, 'default' => null, 'max' => null, 'help' => $help];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array{title: string, description: string|null, fields: array<string, array<string, mixed>>}
     */
    private static function section(string $title, array $fields, ?string $description = null): array
    {
        return ['title' => $title, 'description' => $description, 'fields' => $fields];
    }

    /**
     * @return array{title: string, description: string|null, fields: array<string, array<string, mixed>>}
     */
    private static function seo(string $title, string $description): array
    {
        return self::section('Search engines and sharing', [
            'meta_title' => self::text('Page title in search results', $title, 70, 'Shown in the browser tab and in Google. The site name is added automatically.'),
            'meta_description' => self::textarea('Description in search results', $description, 300, 'One or two sentences. Search engines show about 160 characters.'),
        ], 'Optional: leave empty to keep the original.');
    }

    /**
     * @return array<string, array{label: string, path: string, description: string, sections: list<array<string, mixed>>}>
     */
    private static function pages(): array
    {
        static $pages = null;

        return $pages ??= [
            'who-we-are' => [
                'label' => 'Who We Are',
                'path' => '/who-we-are',
                'description' => 'Mission, vision and values.',
                'sections' => [
                    self::section('Mission card', [
                        'mission_image' => self::image('Picture'),
                        'mission_eyebrow' => self::text('Small heading', 'Our', 40),
                        'mission_title' => self::text('Heading', 'MISSION', 40),
                        'mission_body' => self::rich('Text', '<p>At Canco Cares, we believe in the transformative power of compassion and community.</p><p>Our mission is to create a ripple of positive change in our society, bridging the gap between businesses and communities. We inspire our employees and customers to extend their hands in service to the communities that we cherish, the places we call home.</p><p>Through strategic partnerships, we ignite sparks of hope and progress that will echo into the future, touching lives for generations to come. We are more than a name, we are a movement. A movement committed to nurturing a better tomorrow, embodying the heartfelt spirit of caring that is the essence of our Canadian identity.</p>', 4000, 'The first paragraph is shown larger and centred.'),
                    ]),
                    self::section('Second card', [
                        'vision_image' => self::image('Picture'),
                        'vision_eyebrow' => self::text('Small heading', 'Strategic', 40),
                        'vision_title' => self::text('Heading', 'PARTNERSHIPS', 40, 'On the live site this card is headed “Strategic Partnerships” but holds the vision text; change the heading to “VISION” if that is what you mean.'),
                        'vision_body' => self::rich('Text', '<p>At Canco Cares, we aim to create a future where compassion and community are the cornerstones of societal progress.</p><p>We dream of a world where every individual, regardless of their circumstances, is empowered to thrive and make a meaningful contribution to their community. In the near future, we see ourselves expanding our reach, touching more lives, and strengthening the fabric of our community. We aim to build strong partnerships that align with our mission, fostering a network of support and collaboration. We are committed to enhancing our sustainability, ensuring our efforts today lead to lasting positive change. Above all, we strive to promote inclusivity, creating a space where everyone feels seen, heard, and valued.</p><p>As we look further into the future, we aspire to deepen our impact, becoming a beacon of hope and resilience in our community. We envision ourselves leading through partnerships, championing sustainability, and fostering a culture of inclusivity. Our dream is to leave a lasting legacy, a ripple of positive change that inspires hope and resilience in generations to come. This is the world we are committed to helping build, a world that truly embodies the heartfelt spirit of our Canadian identity.</p>', 6000),
                    ]),
                    self::section('Values', [
                        'values_eyebrow' => self::text('Small heading', 'Our', 40),
                        'values_title' => self::text('Heading', 'VALUES', 40),
                        'values_intro' => self::textarea('Introduction', "At Canco Cares, our values are the guiding principles\nthat inspire and direct our work:", 300, 'A new line starts a new line on the page.'),
                        'value_1_name' => self::text('Value 1 · name', 'Compassion', 40),
                        'value_1_text' => self::textarea('Value 1 · text', 'We believe in the power of empathy and understanding, always approaching our work with kindness and respect for the individuals and communities we serve.', 400),
                        'value_2_name' => self::text('Value 2 · name', 'Community', 40),
                        'value_2_text' => self::textarea('Value 2 · text', 'We value the strength and spirit of community. We strive to foster connections and build relationships that strengthen our collective ability to make a difference.', 400),
                        'value_3_name' => self::text('Value 3 · name', 'Empowerment', 40),
                        'value_3_text' => self::textarea('Value 3 · text', 'We are committed to empowering individuals, enabling them to overcome challenges and reach their full potential.', 400),
                        'value_4_name' => self::text('Value 4 · name', 'Sustainability', 40),
                        'value_4_text' => self::textarea('Value 4 · text', 'We are dedicated to creating solutions that are sustainable, ensuring our efforts lead to lasting positive change.', 400),
                        'value_5_name' => self::text('Value 5 · name', 'Inclusivity', 40),
                        'value_5_text' => self::textarea('Value 5 · text', 'We believe in the importance of inclusivity, ensuring everyone has the opportunity to thrive, regardless of their circumstances.', 400),
                        'value_6_name' => self::text('Value 6 · name', 'Legacy', 40),
                        'value_6_text' => self::textarea('Value 6 · text', 'We are driven by the desire to leave a positive legacy, inspiring hope and resilience in generations to come.', 400),
                    ], 'The six icons stay as they are; change the words.'),
                    self::seo('Who We Are', 'Our mission, vision and values: bridging businesses and communities to create a ripple of positive change across Canada.'),
                ],
            ],

            'our-impact' => [
                'label' => 'Our Impact',
                'path' => '/our-impact',
                'description' => 'The impact story. The big number and its paragraph are edited under Site settings.',
                'sections' => [
                    self::section('Top of the page', [
                        'eyebrow' => self::text('Small heading', 'Every community deserves', 60),
                        'title' => self::text('Heading', 'OUR IMPACT', 40),
                        'lead' => self::textarea('Introduction', 'At Canco Cares, we are already making a significant difference. Our efforts have touched the lives of numerous families and communities, and this is just the beginning. We have ambitious plans for rapid expansion, with the goal of increasing our impact each year.', 600),
                    ]),
                    self::section('Photo and story', [
                        'photo' => self::image('Photo'),
                        'body' => self::rich('Text', '<p>Our contributions are making a significant impact, showcasing the immense value and potential of our work</p><p>Each day, we strive to help an increasing number of individuals.</p><p>Every person we reach, every life we touch, brings us one step closer to our goal.</p><p>Our primary objective at Canco Cares is to give back to communities and families. We are not just an organization; we are a movement. A movement that is creating a ripple of positive change in our society. Our actions today are sowing the seeds for a better tomorrow, a future where compassion and community are the cornerstones of societal progress.</p>', 4000, 'Paragraphs alternate between bold and regular text, as on the original page.'),
                    ]),
                    self::section('Closing', [
                        'closing_lead' => self::textarea('Closing paragraph', 'As we continue on this journey, we look forward to sharing our progress, celebrating our successes, and acknowledging the challenges that inspire us to do better. This is the impact of Canco Cares.', 600),
                        'closing_heading' => self::text('Closing line', 'Together, we are building a legacy of positive change!', 120),
                    ]),
                    self::seo('Our Impact', 'Over {amount} donated to the Make-A-Wish Foundation and counting — see how Canco Cares is changing lives in communities across Canada.'),
                ],
            ],

            'partner-with-us' => [
                'label' => 'Partner With Us',
                'path' => '/partner-with-us',
                'description' => 'How organisations can partner with Canco Cares.',
                'sections' => [
                    self::section('Top of the page', [
                        'title' => self::text('Heading', 'PARTNER', 40),
                        'eyebrow' => self::text('Second heading', 'WITH US', 40),
                        'lead' => self::textarea('Introduction', 'At Canco Cares, we believe in the power of collaboration. Our partnerships play a crucial role in our mission to create a ripple of positive change in our society.', 600),
                    ]),
                    self::section('The two boxes', [
                        'box_1' => self::rich('Left box', '<p>By partnering with us, you join a dedicated network of businesses and communities working together to make a difference.</p><p>Whether you’re a corporation looking to give back, a non-profit organization aligned with our mission, or an individual passionate about community service, there are numerous ways to partner with us. From financial contributions and in-kind donations to volunteering and advocacy, every form of support brings us one step closer to our goal.</p>', 4000),
                        'box_2' => self::rich('Right box', '<p>As a partner, you’ll have the opportunity to contribute to our projects, participate in our events, and make a tangible impact in the communities we serve.</p><p>But the benefits of partnership go beyond that. It’s about being part of a movement, a collective effort to foster compassion, inclusivity, and progress in our society.</p>', 4000),
                    ], 'The two illustrations stay as they are.'),
                    self::section('Closing', [
                        'contact_lead' => self::text('Line above the e-mail address', 'For inquiries, please contact us at', 120, 'The address itself is set under Site settings.'),
                        'closing_lead' => self::textarea('Closing paragraph', 'Together, we can create a future where every individual is empowered to thrive and every community is a beacon of hope and resilience. Thank you for considering a partnership with Canco Cares. We look forward to building a better tomorrow with you.', 600),
                        'button_label' => self::text('Button label', 'Contact Canco Cares', 40),
                    ]),
                    self::seo('Partner With Us', 'Partner with Canco Cares: join a network of businesses and communities creating a ripple of positive change across Canada.'),
                ],
            ],

            'contact-us' => [
                'label' => 'Contact Us',
                'path' => '/contact-us',
                'description' => 'The words around the contact form. The form’s topics are under Inquiry topics.',
                'sections' => [
                    self::section('Top of the page', [
                        'title' => self::text('Heading', 'CONTACT', 40),
                        'eyebrow' => self::text('Second heading', 'CANCO CARES', 40),
                        'lead' => self::textarea('Introduction', 'We’d love to hear from you! Whether you have a question about our projects, want to get involved, or simply want to learn more about Canco Cares, our team is here to help. Your feedback and inquiries are important to us. They help us understand your needs and improve our services. So don’t hesitate to reach out.', 800),
                        'form_heading' => self::text('Line above the form', 'Please fill out the form below with your contact information and message.', 200),
                        'form_note' => self::text('Smaller line above the form', 'We’ll do our best to respond as quickly as possible.', 200),
                    ]),
                    self::section('Below the form', [
                        'thanks' => self::text('Thank-you line', 'Thank you for your interest in Canco Cares', 200),
                        'closing' => self::text('Closing line', 'Together we can create a ripple of positive change in our society.', 200),
                    ]),
                    self::seo('Contact Us', 'Questions, partnerships or ways to get involved — get in touch with the Canco Cares team.'),
                ],
            ],

            'terms-and-conditions' => self::legal(
                'Terms and Conditions',
                '/terms-and-conditions',
                'TERMS AND CONDITIONS',
                'How Canco Cares and Canco Petroleum collect, use and protect personal information, including donor information.',
            ),

            'privacy-policy' => self::legal(
                'Privacy Policy',
                '/privacy-policy',
                'PRIVACY POLICY',
                'How Canco Cares collects, uses and protects your personal information, including donor information.',
            ),
        ];
    }

    /**
     * A long-text page. Until the client supplies final wording both legal pages start from the text
     * published on cancocares.ca (Canco Petroleum's personal-information policy); an editor replaces it.
     *
     * @return array{label: string, path: string, description: string, sections: list<array<string, mixed>>}
     */
    private static function legal(string $label, string $path, string $title, string $description): array
    {
        $default = (string) @file_get_contents(database_path('seeders/data/pages/legal-default.html'));

        return [
            'label' => $label,
            'path' => $path,
            'description' => 'A long text page. The first version is the text from the old website: replace it with the final wording.',
            'sections' => [
                self::section('Heading', [
                    'eyebrow' => self::text('Small heading', 'CANCO CARES', 40),
                    'title' => self::text('Heading', $title, 60),
                    'updated_on' => self::text('Last updated', '', 60, 'For example “November 21, 2026”. Shown under the heading; leave empty to hide it.'),
                ]),
                self::section('Text', [
                    'body' => self::rich('Page text', $default, 300000, 'Use headings, lists and links from the toolbar.'),
                ]),
                self::seo($label, $description),
            ],
        ];
    }
}
