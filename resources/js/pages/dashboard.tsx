import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    CalendarDays,
    CircleDollarSign,
    HeartHandshake,
    Image as ImageIcon,
    Inbox,
    LayoutPanelTop,
    Newspaper,
    Plus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import DonationController from '@/actions/App/Http/Controllers/Admin/DonationController';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import HomePageController from '@/actions/App/Http/Controllers/Admin/HomePageController';
import InquiryController from '@/actions/App/Http/Controllers/Admin/InquiryController';
import MediaController from '@/actions/App/Http/Controllers/Admin/MediaController';
import SiteSettingsController from '@/actions/App/Http/Controllers/Admin/SiteSettingsController';
import StoryController from '@/actions/App/Http/Controllers/Admin/StoryController';
import DonationStatusBadge from '@/components/donations/donation-status-badge';
import EventStateBadge from '@/components/events/event-state-badge';
import InquiryStatusBadge from '@/components/inquiries/inquiry-status-badge';
import StoryStateBadge from '@/components/stories/story-state-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useHydrated } from '@/hooks/use-hydrated';
import { formatEventStart, formatRelative } from '@/lib/date';
import { formatCents } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { DashboardData } from '@/types';

const compactMoney = (cents: number): string =>
    new Intl.NumberFormat('en-CA', {
        style: 'currency',
        currency: 'CAD',
        maximumFractionDigits: cents >= 100_000 ? 0 : 2,
    }).format(cents / 100);

/** A headline number with a label, an icon and one line of context; links to the screen behind it. */
function Stat({
    icon: Icon,
    label,
    value,
    note,
    href,
    tone = 'default',
}: {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
    note?: ReactNode;
    href?: React.ComponentProps<typeof Link>['href'];
    tone?: 'default' | 'attention';
}) {
    const body = (
        <Card
            className={cn(
                'h-full gap-3 py-5 transition-colors',
                href && 'hover:bg-muted/40',
                tone === 'attention' &&
                    'border-orange-300 bg-orange-50/60 dark:border-orange-800 dark:bg-orange-950/20',
            )}
        >
            <CardHeader className="flex-row items-center justify-between gap-2 px-5">
                <CardDescription className="font-medium">
                    {label}
                </CardDescription>
                <Icon
                    className="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
            </CardHeader>
            <CardContent className="px-5">
                <p className="text-2xl font-semibold tracking-tight tabular-nums">
                    {value}
                </p>
                {note && (
                    <p className="mt-1 text-xs text-muted-foreground">{note}</p>
                )}
            </CardContent>
        </Card>
    );

    return href ? (
        <Link
            href={href}
            className="block rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            {body}
        </Link>
    ) : (
        body
    );
}

/** A card with a title, a "see all" link and a list (or an empty message). */
function Panel({
    title,
    description,
    action,
    empty,
    children,
}: {
    title: string;
    description?: string;
    action?: { label: string; href: React.ComponentProps<typeof Link>['href'] };
    empty?: string;
    children?: ReactNode;
}) {
    return (
        <Card className="gap-4">
            <CardHeader className="flex-row items-start justify-between gap-3">
                <div className="grid gap-1">
                    <CardTitle className="text-base">{title}</CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
                {action && (
                    <Button asChild variant="ghost" size="sm">
                        <Link href={action.href}>
                            {action.label} <ArrowRight />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {children || (
                    <p className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {empty}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function Row({
    href,
    title,
    meta,
    badge,
}: {
    href: React.ComponentProps<typeof Link>['href'];
    title: ReactNode;
    meta: ReactNode;
    badge?: ReactNode;
}) {
    return (
        <li className="min-w-0">
            <Link
                href={href}
                className="-mx-2 flex items-center justify-between gap-3 rounded-md px-2 py-2 transition-colors hover:bg-muted/60 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span className="min-w-0">
                    <span className="block truncate text-sm font-medium">
                        {title}
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">
                        {meta}
                    </span>
                </span>
                {badge}
            </Link>
        </li>
    );
}

/** Daily donations as bars. Plain SVG: no chart library for one picture. */
function DonationsChart({
    series,
}: {
    series: { date: string; cents: number }[];
}) {
    const max = Math.max(1, ...series.map((point) => point.cents));
    const total = series.reduce((sum, point) => sum + point.cents, 0);
    const width = 600;
    const height = 140;
    const gap = 4;
    const bar = (width - gap * (series.length - 1)) / series.length;
    // Fixed locale and zone: the server and the browser must print the same text.
    const day = new Intl.DateTimeFormat('en-CA', {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });
    const label = (date: string) => day.format(new Date(`${date}T00:00:00Z`));

    return (
        <figure className="m-0">
            <svg
                viewBox={`0 0 ${width} ${height + 22}`}
                role="img"
                aria-label={`Donations in the last ${series.length} days: ${formatCents(total)} in total. The busiest day was ${formatCents(max === 1 && total === 0 ? 0 : max)}.`}
                className="h-auto w-full text-primary"
            >
                <line
                    x1="0"
                    x2={width}
                    y1={height}
                    y2={height}
                    className="stroke-border"
                />
                {series.map((point, index) => {
                    const barHeight =
                        point.cents === 0
                            ? 2
                            : Math.max(4, (point.cents / max) * (height - 8));

                    return (
                        <rect
                            key={point.date}
                            x={index * (bar + gap)}
                            y={height - barHeight}
                            width={bar}
                            height={barHeight}
                            rx={2}
                            fill="currentColor"
                            opacity={point.cents === 0 ? 0.18 : 0.9}
                        >
                            <title>{`${label(point.date)}: ${formatCents(point.cents)}`}</title>
                        </rect>
                    );
                })}
                <text
                    x="0"
                    y={height + 16}
                    className="fill-muted-foreground text-[11px]"
                >
                    {label(series[0].date)}
                </text>
                <text
                    x={width}
                    y={height + 16}
                    textAnchor="end"
                    className="fill-muted-foreground text-[11px]"
                >
                    {label(series[series.length - 1].date)}
                </text>
            </svg>
        </figure>
    );
}

function Delta({ now, before }: { now: number; before: number }) {
    if (before === 0) {
        return <>{now === 0 ? 'No gifts yet' : 'First gifts of the period'}</>;
    }

    const change = Math.round(((now - before) / before) * 100);
    const Icon = change >= 0 ? ArrowUpRight : ArrowDownRight;

    return (
        <span className="inline-flex items-center gap-0.5">
            <Icon className="size-3" aria-hidden="true" />
            {Math.abs(change)}% {change >= 0 ? 'more' : 'less'} than last month
        </span>
    );
}

export default function Dashboard({
    stories,
    events,
    inquiries,
    library,
    site,
    donations,
    system,
}: DashboardData) {
    const { auth } = usePage().props;
    const hydrated = useHydrated();
    // Clock- and locale-dependent text waits for the browser, so it cannot mismatch the server HTML.
    const ago = (iso: string | null) => (hydrated ? formatRelative(iso) : '');
    const startsAt = (iso: string, zone: string, allDay: boolean) =>
        hydrated ? formatEventStart(iso, zone, allDay) : '';
    const firstName = auth.user.name.split(' ')[0];
    const today = hydrated
        ? new Intl.DateTimeFormat(undefined, {
              weekday: 'long',
              month: 'long',
              day: 'numeric',
          }).format(new Date())
        : ' ';

    // Things that need a person, most urgent first.
    const attention: {
        text: string;
        href: React.ComponentProps<typeof Link>['href'];
    }[] = [];

    if (inquiries.new > 0) {
        attention.push({
            text: `${inquiries.new} new ${inquiries.new === 1 ? 'inquiry is' : 'inquiries are'} waiting for a reply`,
            href: InquiryController.index({ query: { view: 'new' } }),
        });
    }

    if (donations && donations.failed_week > 0) {
        attention.push({
            text: `${donations.failed_week} failed donation${donations.failed_week === 1 ? '' : 's'} in the last 7 days`,
            href: DonationController.index({ query: { view: 'failed' } }),
        });
    }

    if (donations && donations.pending > 0) {
        attention.push({
            text: `${donations.pending} donation${donations.pending === 1 ? ' has' : 's have'} been pending for over an hour`,
            href: DonationController.index({ query: { view: 'pending' } }),
        });
    }

    if (system && (system.queue_failed ?? 0) > 0) {
        attention.push({
            text: `${system.queue_failed} background job${system.queue_failed === 1 ? '' : 's'} failed: emails or website refreshes may be missing`,
            href: dashboard(),
        });
    }

    if (system && (system.queue_waiting ?? 0) > 25) {
        attention.push({
            text: `${system.queue_waiting} background jobs are waiting: is the queue worker running?`,
            href: dashboard(),
        });
    }

    if (system?.gateway_problem && !site.donations_open) {
        attention.push({
            text: `Online donations are closed: ${system.gateway_problem}`,
            href: SiteSettingsController.edit(),
        });
    }

    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">{today}</p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Welcome back, {firstName}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild>
                            <Link href={StoryController.create()}>
                                <Plus /> New story
                            </Link>
                        </Button>
                        <Button asChild variant="secondary">
                            <Link href={EventController.create()}>
                                <Plus /> New event
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={HomePageController.edit()}>
                                <LayoutPanelTop /> Edit home page
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={MediaController.index()}>
                                <ImageIcon /> Media
                            </Link>
                        </Button>
                    </div>
                </div>

                {attention.length > 0 && (
                    <section
                        aria-label="Needs attention"
                        className="rounded-xl border border-orange-300 bg-orange-50/70 p-4 dark:border-orange-800 dark:bg-orange-950/20"
                    >
                        <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-orange-900 dark:text-orange-200">
                            <AlertTriangle className="size-4" /> Needs attention
                        </h2>
                        <ul className="m-0 grid list-none gap-1 p-0">
                            {attention.map((item) => (
                                <li key={item.text}>
                                    <Link
                                        href={item.href}
                                        className="inline-flex items-center gap-1 text-sm text-orange-950 underline-offset-4 hover:underline dark:text-orange-100"
                                    >
                                        {item.text}{' '}
                                        <ArrowRight className="size-3" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section
                    aria-label="At a glance"
                    className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
                >
                    {donations && (
                        <>
                            <Stat
                                icon={CircleDollarSign}
                                label="Raised this month"
                                value={compactMoney(donations.this_month_cents)}
                                note={
                                    <Delta
                                        now={donations.this_month_cents}
                                        before={donations.last_month_cents}
                                    />
                                }
                                href={DonationController.index()}
                            />
                            <Stat
                                icon={HeartHandshake}
                                label="Raised in total"
                                value={compactMoney(donations.total_cents)}
                                note={`${donations.gifts} gift${donations.gifts === 1 ? '' : 's'} · average ${formatCents(donations.average_cents)}`}
                                href={DonationController.index({
                                    query: { view: 'succeeded', mode: 'real' },
                                })}
                            />
                        </>
                    )}
                    <Stat
                        icon={Inbox}
                        label="New inquiries"
                        value={inquiries.new}
                        note={`${inquiries.last_30_days} in the last 30 days`}
                        href={InquiryController.index()}
                        tone={inquiries.new > 0 ? 'attention' : 'default'}
                    />
                    <Stat
                        icon={CalendarDays}
                        label="Upcoming events"
                        value={events.upcoming}
                        note={
                            events.drafts > 0
                                ? `${events.drafts} draft${events.drafts === 1 ? '' : 's'} to finish`
                                : 'All events are live'
                        }
                        href={EventController.index()}
                    />
                    <Stat
                        icon={Newspaper}
                        label="Published stories"
                        value={stories.published}
                        note={[
                            stories.drafts > 0 && `${stories.drafts} drafts`,
                            stories.scheduled > 0 &&
                                `${stories.scheduled} scheduled`,
                            `${stories.featured} featured`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                        href={StoryController.index()}
                    />
                    {!donations && (
                        <Stat
                            icon={ImageIcon}
                            label="Pictures in the library"
                            value={library.images}
                            href={MediaController.index()}
                        />
                    )}
                </section>

                {donations && (
                    <div className="grid gap-6 xl:grid-cols-3">
                        <Card className="gap-4 xl:col-span-2">
                            <CardHeader className="flex-row items-start justify-between gap-3">
                                <div className="grid gap-1">
                                    <CardTitle className="text-base">
                                        Donations, last {donations.chart.days}{' '}
                                        days
                                    </CardTitle>
                                    <CardDescription>
                                        Real gifts, net of refunds.{' '}
                                        {donations.test_count > 0 &&
                                            `${donations.test_count} test donation${donations.test_count === 1 ? ' is' : 's are'} not counted.`}
                                    </CardDescription>
                                </div>
                                <p className="text-right text-sm">
                                    <span className="block text-xs text-muted-foreground">
                                        Website shows
                                    </span>
                                    <strong>{site.impact_text || '—'}</strong>
                                </p>
                            </CardHeader>
                            <CardContent>
                                <DonationsChart
                                    series={donations.chart.series}
                                />
                            </CardContent>
                        </Card>

                        <Panel
                            title="Latest gifts"
                            description="Donor names are shortened here."
                            action={{
                                label: 'All donations',
                                href: DonationController.index(),
                            }}
                            empty="No donations yet."
                        >
                            {donations.recent.length > 0 && (
                                <ul className="m-0 grid list-none grid-cols-[minmax(0,1fr)] p-0">
                                    {donations.recent.map((gift) => (
                                        <Row
                                            key={gift.id}
                                            href={DonationController.show(
                                                gift.id,
                                            )}
                                            title={
                                                <>
                                                    {formatCents(
                                                        gift.amount_cents,
                                                    )}{' '}
                                                    <span className="font-normal text-muted-foreground">
                                                        from {gift.donor}
                                                    </span>
                                                </>
                                            }
                                            meta={`${gift.event ?? 'General gift'}${gift.is_test ? ' · test' : ''} · ${ago(gift.date)}`}
                                            badge={
                                                <DonationStatusBadge
                                                    status={gift.status}
                                                />
                                            }
                                        />
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title="Recent inquiries"
                        action={{
                            label: 'Inbox',
                            href: InquiryController.index(),
                        }}
                        empty="No messages yet."
                    >
                        {inquiries.recent.length > 0 && (
                            <ul className="m-0 grid list-none grid-cols-[minmax(0,1fr)] p-0">
                                {inquiries.recent.map((inquiry) => (
                                    <Row
                                        key={inquiry.id}
                                        href={InquiryController.show(
                                            inquiry.id,
                                        )}
                                        title={
                                            <>
                                                {inquiry.name}
                                                {inquiry.topic && (
                                                    <span className="font-normal text-muted-foreground">
                                                        {' '}
                                                        · {inquiry.topic}
                                                    </span>
                                                )}
                                            </>
                                        }
                                        meta={`${inquiry.excerpt} · ${ago(inquiry.created_at)}`}
                                        badge={
                                            <InquiryStatusBadge
                                                status={inquiry.status}
                                            />
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Coming up"
                        description="The next events on the website."
                        action={{
                            label: 'All events',
                            href: EventController.index(),
                        }}
                        empty="No upcoming events. Add one and it appears on the Upcoming Events page."
                    >
                        {events.next.length > 0 && (
                            <ul className="m-0 grid list-none grid-cols-[minmax(0,1fr)] p-0">
                                {events.next.map((event) => (
                                    <Row
                                        key={event.id}
                                        href={EventController.edit(event.id)}
                                        title={event.title}
                                        meta={`${startsAt(event.starts_at, event.timezone, event.is_all_day)}${event.place ? ` · ${event.place}` : ''}`}
                                        badge={
                                            <EventStateBadge
                                                state={event.state}
                                            />
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Recently edited stories"
                        action={{
                            label: 'All stories',
                            href: StoryController.index(),
                        }}
                        empty="No stories yet."
                    >
                        {stories.recent.length > 0 && (
                            <ul className="m-0 grid list-none grid-cols-[minmax(0,1fr)] p-0">
                                {stories.recent.map((story) => (
                                    <Row
                                        key={story.id}
                                        href={StoryController.edit(story.id)}
                                        title={story.title}
                                        meta={`Edited ${ago(story.updated_at)}`}
                                        badge={
                                            <StoryStateBadge
                                                state={story.state}
                                            />
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Quick links"
                        description="Where editors go most often."
                    >
                        <ul className="m-0 grid list-none gap-1 p-0 text-sm">
                            {[
                                {
                                    label: 'Write a story',
                                    href: StoryController.create(),
                                },
                                {
                                    label: 'Schedule an event',
                                    href: EventController.create(),
                                },
                                {
                                    label: 'Change the home page words and pictures',
                                    href: HomePageController.edit(),
                                },
                                {
                                    label: `Browse the media library (${library.images} pictures)`,
                                    href: MediaController.index(),
                                },
                            ].map((link) => (
                                <li key={link.label}>
                                    <Link
                                        href={link.href}
                                        className="-mx-2 flex items-center justify-between rounded-md px-2 py-2 hover:bg-muted/60"
                                    >
                                        {link.label}
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
