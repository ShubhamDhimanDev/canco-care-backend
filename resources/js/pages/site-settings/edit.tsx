import { Form, Head } from '@inertiajs/react';
import {
    Facebook,
    HandCoins,
    Instagram,
    Link2,
    Linkedin,
    ReceiptText,
    TrendingUp,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import SiteSettingsController from '@/actions/App/Http/Controllers/Admin/SiteSettingsController';
import AlertError from '@/components/alert-error';
import CheckField from '@/components/check-field';
import FormActionBar from '@/components/form-action-bar';
import FormField from '@/components/form-field';
import SectionCard from '@/components/section-card';
import { Label } from '@/components/ui/label';
import { useFormDirty } from '@/hooks/use-form-dirty';
import { cn } from '@/lib/utils';
import type { SiteSettingsValues } from '@/types';

type Props = {
    values: SiteSettingsValues;
    impactPreview: {
        actual_text: string;
        raised_text: string;
        showing: string;
    };
    gateway: { problem: string | null; available: boolean };
};

const PRESET_BOXES = 6;

const SECTIONS = [
    { id: 'impact', label: 'Impact figure', icon: TrendingUp },
    { id: 'donations', label: 'Online donations', icon: HandCoins },
    { id: 'receipts', label: 'Tax receipts', icon: ReceiptText },
    { id: 'links', label: 'Links and contact', icon: Link2 },
];

function ModeOption({
    value,
    checked,
    onSelect,
    title,
    children,
}: {
    value: 'custom' | 'actual';
    checked: boolean;
    onSelect: () => void;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors',
                checked ? 'border-primary bg-primary/5' : 'hover:bg-muted/30',
            )}
        >
            <input
                type="radio"
                name="impact[mode]"
                value={value}
                checked={checked}
                onChange={onSelect}
                className="mt-1 size-4 accent-primary"
            />
            <span className="grid gap-1">
                <span className="font-medium">{title}</span>
                <span className="text-sm text-muted-foreground">
                    {children}
                </span>
            </span>
        </label>
    );
}

/** A link field with the network's icon beside it. */
function SocialField({
    icon: Icon,
    ...field
}: {
    icon: LucideIcon;
    name: string;
    label: string;
    defaultValue: string;
    error?: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <Icon
                className="mt-8 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div className="flex-1">
                <FormField type="url" placeholder="https://" {...field} />
            </div>
        </div>
    );
}

export default function SiteSettingsEdit({
    values,
    impactPreview,
    gateway,
}: Props) {
    const { dirty, clear, bindings } = useFormDirty();
    const [mode, setMode] = useState(values.impact.mode);
    const [amountText, setAmountText] = useState(values.impact.amount_text);
    const [caption, setCaption] = useState(values.impact.caption);
    const [presetValues, setPresetValues] = useState<string[]>(() =>
        Array.from({ length: PRESET_BOXES }, (_, index) =>
            String(values.donations.presets[index] ?? ''),
        ),
    );

    const figure = mode === 'custom' ? amountText : impactPreview.actual_text;

    return (
        <>
            <Head title="Site settings" />

            <div className="mx-auto w-full max-w-6xl p-4">
                <Form
                    {...SiteSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={clear}
                    className="space-y-6"
                    {...bindings}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <FormActionBar
                                title="Site settings"
                                subtitle="Impact figure, links, donation options and tax receipt details."
                                submitLabel="Save settings"
                                processing={processing}
                                dirty={dirty}
                                saved={recentlySuccessful}
                            />

                            {Object.keys(errors).length > 0 && (
                                <div role="alert">
                                    <AlertError
                                        errors={Object.values(errors)}
                                        title="The settings were not saved"
                                    />
                                </div>
                            )}

                            <div className="grid items-start gap-6 lg:grid-cols-[13rem_minmax(0,1fr)]">
                                <nav
                                    aria-label="Sections of the settings"
                                    className="flex flex-wrap gap-2 lg:sticky lg:top-24 lg:flex-col lg:gap-1"
                                >
                                    {SECTIONS.map(
                                        ({ id, label, icon: Icon }) => (
                                            <a
                                                key={id}
                                                href={`#${id}`}
                                                className="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            >
                                                <Icon
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {label}
                                            </a>
                                        ),
                                    )}
                                </nav>

                                <div className="space-y-6">
                                    <SectionCard
                                        id="impact"
                                        icon={TrendingUp}
                                        title="Our impact figure"
                                        description={
                                            <>
                                                The big number on the home page
                                                and the Our Impact page. Right
                                                now the website shows{' '}
                                                <strong>
                                                    {impactPreview.showing}
                                                </strong>
                                                .
                                            </>
                                        }
                                    >
                                        <div
                                            aria-hidden="true"
                                            className="rounded-lg bg-[#1d376b] px-6 py-5 text-center text-white"
                                        >
                                            <p className="text-xs tracking-widest uppercase opacity-70">
                                                Preview
                                            </p>
                                            <p className="mt-1 text-4xl font-bold tracking-wide">
                                                {figure || '—'}
                                            </p>
                                            <p className="mt-1 text-sm font-medium opacity-90">
                                                {caption}
                                            </p>
                                        </div>

                                        <fieldset className="grid gap-3">
                                            <legend className="sr-only">
                                                What the impact figure shows
                                            </legend>
                                            <ModeOption
                                                value="custom"
                                                checked={mode === 'custom'}
                                                onSelect={() =>
                                                    setMode('custom')
                                                }
                                                title="A number I type"
                                            >
                                                Shows exactly what you write,
                                                for example “$80,000” or “Over
                                                $1 million”.
                                            </ModeOption>
                                            <ModeOption
                                                value="actual"
                                                checked={mode === 'actual'}
                                                onSelect={() =>
                                                    setMode('actual')
                                                }
                                                title="Actual donations"
                                            >
                                                Counts up on its own: the
                                                starting amount below plus every
                                                real donation taken on this
                                                website (after refunds). Test
                                                donations never count. Today
                                                that would be{' '}
                                                <strong>
                                                    {impactPreview.actual_text}
                                                </strong>{' '}
                                                ({impactPreview.raised_text}{' '}
                                                from this website).
                                            </ModeOption>
                                        </fieldset>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField
                                                name="impact[amount_text]"
                                                label="Number to show (when you type it)"
                                                defaultValue={
                                                    values.impact.amount_text
                                                }
                                                maxLength={40}
                                                onValueChange={setAmountText}
                                                error={
                                                    errors['impact.amount_text']
                                                }
                                            />
                                            <FormField
                                                name="impact[starting_amount]"
                                                label="Starting amount, in dollars (for actual donations)"
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                defaultValue={
                                                    values.impact
                                                        .starting_amount
                                                }
                                                help="Money raised before this website, so the figure does not drop to zero. Use 0 to count only donations made here."
                                                error={
                                                    errors[
                                                        'impact.starting_amount'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <FormField
                                            name="impact[caption]"
                                            label="Words under the number"
                                            defaultValue={values.impact.caption}
                                            maxLength={60}
                                            counter
                                            onValueChange={setCaption}
                                            error={errors['impact.caption']}
                                        />
                                        <FormField
                                            name="impact[blurb]"
                                            label="Paragraph above the number"
                                            defaultValue={values.impact.blurb}
                                            rows={4}
                                            maxLength={1000}
                                            counter
                                            error={errors['impact.blurb']}
                                        />
                                    </SectionCard>

                                    <SectionCard
                                        id="donations"
                                        icon={HandCoins}
                                        title="Online donations"
                                        description="The Donate button opens a form. Which events appear in its “Support an event” list is set on each event."
                                    >
                                        {gateway.problem && (
                                            <p
                                                role="note"
                                                className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
                                            >
                                                {gateway.problem} Donations stay
                                                closed until that is sorted out.
                                            </p>
                                        )}
                                        <CheckField
                                            name="donations[enabled]"
                                            icon={HandCoins}
                                            label="Accept online donations"
                                            description="When off, the Donate button shows the “coming soon” message."
                                            defaultChecked={
                                                values.donations.enabled
                                            }
                                        />
                                        <div className="grid gap-2">
                                            <Label>
                                                Suggested amounts (dollars)
                                            </Label>
                                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                                                {presetValues.map(
                                                    (preset, index) => (
                                                        <div
                                                            key={index}
                                                            className="flex items-center rounded-md border shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
                                                        >
                                                            <span
                                                                className="pl-3 text-sm text-muted-foreground"
                                                                aria-hidden="true"
                                                            >
                                                                $
                                                            </span>
                                                            <input
                                                                name="donations[presets][]"
                                                                type="number"
                                                                min={1}
                                                                max={10000}
                                                                step={1}
                                                                value={preset}
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    const next =
                                                                        event
                                                                            .target
                                                                            .value;

                                                                    setPresetValues(
                                                                        (
                                                                            current,
                                                                        ) =>
                                                                            current.map(
                                                                                (
                                                                                    item,
                                                                                    i,
                                                                                ) =>
                                                                                    i ===
                                                                                    index
                                                                                        ? next
                                                                                        : item,
                                                                            ),
                                                                    );
                                                                }}
                                                                aria-label={`Suggested amount ${index + 1}`}
                                                                className="h-9 w-full min-w-0 bg-transparent px-1 pr-2 text-sm outline-none"
                                                            />
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Up to six whole-dollar buttons.
                                                Leave the extras empty.
                                            </p>
                                            {Object.entries(errors)
                                                .filter(([key]) =>
                                                    key.startsWith(
                                                        'donations.presets',
                                                    ),
                                                )
                                                .map(([key, message]) => (
                                                    <p
                                                        key={key}
                                                        className="text-sm text-red-600 dark:text-red-400"
                                                    >
                                                        {message}
                                                    </p>
                                                ))}
                                        </div>
                                        <FormField
                                            name="donations[min]"
                                            label="Smallest gift (dollars)"
                                            type="number"
                                            min={1}
                                            max={10000}
                                            step="0.01"
                                            defaultValue={values.donations.min}
                                            error={errors['donations.min']}
                                        />
                                        {errors['donations.enabled'] && (
                                            <p className="text-sm text-red-600 dark:text-red-400">
                                                {errors['donations.enabled']}
                                            </p>
                                        )}
                                    </SectionCard>

                                    <SectionCard
                                        id="receipts"
                                        icon={ReceiptText}
                                        title="Tax receipt details"
                                        description="Printed on the official receipt emailed to every donor. Check these carefully: they are legal details."
                                    >
                                        <FormField
                                            name="receipts[charity_name]"
                                            label="Charity name"
                                            defaultValue={
                                                values.receipts.charity_name
                                            }
                                            maxLength={120}
                                            required
                                            error={
                                                errors['receipts.charity_name']
                                            }
                                        />
                                        <FormField
                                            name="receipts[address]"
                                            label="Charity address"
                                            defaultValue={
                                                values.receipts.address
                                            }
                                            maxLength={200}
                                            required
                                            error={errors['receipts.address']}
                                        />
                                        <FormField
                                            name="receipts[registration_number]"
                                            label="Charitable registration number"
                                            defaultValue={
                                                values.receipts
                                                    .registration_number
                                            }
                                            maxLength={40}
                                            required
                                            error={
                                                errors[
                                                    'receipts.registration_number'
                                                ]
                                            }
                                        />
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField
                                                name="receipts[signatory_name]"
                                                label="Signed by"
                                                defaultValue={
                                                    values.receipts
                                                        .signatory_name
                                                }
                                                maxLength={80}
                                                required
                                                error={
                                                    errors[
                                                        'receipts.signatory_name'
                                                    ]
                                                }
                                            />
                                            <FormField
                                                name="receipts[signatory_label]"
                                                label="Caption under the signature"
                                                defaultValue={
                                                    values.receipts
                                                        .signatory_label
                                                }
                                                maxLength={60}
                                                required
                                                error={
                                                    errors[
                                                        'receipts.signatory_label'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <FormField
                                            name="receipts[reply_to]"
                                            label="Donors’ replies go to"
                                            type="email"
                                            defaultValue={
                                                values.receipts.reply_to
                                            }
                                            maxLength={190}
                                            error={errors['receipts.reply_to']}
                                        />
                                    </SectionCard>

                                    <SectionCard
                                        id="links"
                                        icon={Link2}
                                        title="Links and contact"
                                        description="Shown in the website's footer and on the contact page."
                                    >
                                        <FormField
                                            name="contact[inquiry_email]"
                                            label="Public contact email"
                                            type="email"
                                            defaultValue={
                                                values.contact.inquiry_email
                                            }
                                            maxLength={190}
                                            help="Shown on the website. Where contact-form messages are delivered is set under Inquiry topics."
                                            error={
                                                errors['contact.inquiry_email']
                                            }
                                        />
                                        <SocialField
                                            icon={Facebook}
                                            name="social[facebook]"
                                            label="Facebook"
                                            defaultValue={
                                                values.social.facebook
                                            }
                                            error={errors['social.facebook']}
                                        />
                                        <SocialField
                                            icon={Instagram}
                                            name="social[instagram]"
                                            label="Instagram"
                                            defaultValue={
                                                values.social.instagram
                                            }
                                            error={errors['social.instagram']}
                                        />
                                        <SocialField
                                            icon={Linkedin}
                                            name="social[linkedin]"
                                            label="LinkedIn"
                                            defaultValue={
                                                values.social.linkedin
                                            }
                                            error={errors['social.linkedin']}
                                        />
                                    </SectionCard>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

SiteSettingsEdit.layout = {
    breadcrumbs: [
        { title: 'Site settings', href: SiteSettingsController.edit() },
    ],
};
