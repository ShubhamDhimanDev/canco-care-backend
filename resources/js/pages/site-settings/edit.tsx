import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import SiteSettingsController from '@/actions/App/Http/Controllers/Admin/SiteSettingsController';
import AlertError from '@/components/alert-error';
import CheckField from '@/components/check-field';
import FormField from '@/components/form-field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
                'flex cursor-pointer items-start gap-3 rounded-md border p-4 transition-colors',
                checked ? 'border-primary bg-muted/50' : 'hover:bg-muted/30',
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

export default function SiteSettingsEdit({
    values,
    impactPreview,
    gateway,
}: Props) {
    const [mode, setMode] = useState(values.impact.mode);
    const presets = Array.from(
        { length: PRESET_BOXES },
        (_, index) => values.donations.presets[index] ?? '',
    );

    return (
        <>
            <Head title="Site settings" />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4">
                <Heading
                    title="Site settings"
                    description="The impact figure, links, donation options and the details printed on tax receipts."
                />

                <Form
                    {...SiteSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {Object.keys(errors).length > 0 && (
                                <div role="alert">
                                    <AlertError
                                        errors={Object.values(errors)}
                                        title="The settings were not saved"
                                    />
                                </div>
                            )}

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Our impact figure
                                    </CardTitle>
                                    <CardDescription>
                                        The big number on the home page and the
                                        Our Impact page. Right now the website
                                        shows{' '}
                                        <strong>{impactPreview.showing}</strong>
                                        .
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <fieldset className="grid gap-3">
                                        <legend className="sr-only">
                                            What the impact figure shows
                                        </legend>
                                        <ModeOption
                                            value="custom"
                                            checked={mode === 'custom'}
                                            onSelect={() => setMode('custom')}
                                            title="A number I type"
                                        >
                                            Shows exactly what you write, for
                                            example “$80,000” or “Over $1
                                            million”.
                                        </ModeOption>
                                        <ModeOption
                                            value="actual"
                                            checked={mode === 'actual'}
                                            onSelect={() => setMode('actual')}
                                            title="Actual donations"
                                        >
                                            Counts up on its own: the starting
                                            amount below plus every real
                                            donation taken on this website
                                            (after refunds). Test donations
                                            never count. Today that would be{' '}
                                            <strong>
                                                {impactPreview.actual_text}
                                            </strong>{' '}
                                            ({impactPreview.raised_text} from
                                            this website).
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
                                            error={errors['impact.amount_text']}
                                        />
                                        <FormField
                                            name="impact[starting_amount]"
                                            label="Starting amount, in dollars (for actual donations)"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            defaultValue={
                                                values.impact.starting_amount
                                            }
                                            help="Money raised before this website, so the figure does not drop to zero. Use 0 to count only donations made here."
                                            error={
                                                errors['impact.starting_amount']
                                            }
                                        />
                                    </div>
                                    <FormField
                                        name="impact[caption]"
                                        label="Words under the number"
                                        defaultValue={values.impact.caption}
                                        maxLength={60}
                                        error={errors['impact.caption']}
                                    />
                                    <FormField
                                        name="impact[blurb]"
                                        label="Paragraph above the number"
                                        defaultValue={values.impact.blurb}
                                        rows={4}
                                        maxLength={1000}
                                        error={errors['impact.blurb']}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Online donations
                                    </CardTitle>
                                    <CardDescription>
                                        The Donate button opens a form. Which
                                        events appear in its “Support an event”
                                        list is set on each event.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
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
                                        <div className="grid grid-cols-3 gap-3 sm:grid-cols-6">
                                            {presets.map((preset, index) => (
                                                <input
                                                    key={index}
                                                    name="donations[presets][]"
                                                    type="number"
                                                    min={1}
                                                    max={10000}
                                                    step={1}
                                                    defaultValue={preset}
                                                    aria-label={`Suggested amount ${index + 1}`}
                                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                                />
                                            ))}
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
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Tax receipt details
                                    </CardTitle>
                                    <CardDescription>
                                        Printed on the official receipt emailed
                                        to every donor. Check these carefully:
                                        they are legal details.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <FormField
                                        name="receipts[charity_name]"
                                        label="Charity name"
                                        defaultValue={
                                            values.receipts.charity_name
                                        }
                                        maxLength={120}
                                        required
                                        error={errors['receipts.charity_name']}
                                    />
                                    <FormField
                                        name="receipts[address]"
                                        label="Charity address"
                                        defaultValue={values.receipts.address}
                                        maxLength={200}
                                        required
                                        error={errors['receipts.address']}
                                    />
                                    <FormField
                                        name="receipts[registration_number]"
                                        label="Charitable registration number"
                                        defaultValue={
                                            values.receipts.registration_number
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
                                                values.receipts.signatory_name
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
                                                values.receipts.signatory_label
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
                                        defaultValue={values.receipts.reply_to}
                                        maxLength={190}
                                        error={errors['receipts.reply_to']}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Links and contact
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <FormField
                                        name="contact[inquiry_email]"
                                        label="Public contact email"
                                        type="email"
                                        defaultValue={
                                            values.contact.inquiry_email
                                        }
                                        maxLength={190}
                                        help="Shown on the website. Where contact-form messages are delivered is set under Inquiry topics."
                                        error={errors['contact.inquiry_email']}
                                    />
                                    <FormField
                                        name="social[facebook]"
                                        label="Facebook"
                                        type="url"
                                        defaultValue={values.social.facebook}
                                        placeholder="https://"
                                        error={errors['social.facebook']}
                                    />
                                    <FormField
                                        name="social[instagram]"
                                        label="Instagram"
                                        type="url"
                                        defaultValue={values.social.instagram}
                                        placeholder="https://"
                                        error={errors['social.instagram']}
                                    />
                                    <FormField
                                        name="social[linkedin]"
                                        label="LinkedIn"
                                        type="url"
                                        defaultValue={values.social.linkedin}
                                        placeholder="https://"
                                        error={errors['social.linkedin']}
                                    />
                                </CardContent>
                            </Card>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />} Save settings
                            </Button>
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
