import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import StoryController from '@/actions/App/Http/Controllers/Admin/StoryController';
import Heading from '@/components/heading';
import StoryStateBadge from '@/components/stories/story-state-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/date';
import { cn } from '@/lib/utils';
import type { Paginated, StoryCounts, StoryFilter, StoryRow } from '@/types';

type Props = {
    stories: Paginated<StoryRow>;
    filters: { status: StoryFilter; search: string };
    counts: StoryCounts;
    can: { forceDelete: boolean };
};

const TABS: { key: StoryFilter; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'published', label: 'Published' },
    { key: 'scheduled', label: 'Scheduled' },
    { key: 'draft', label: 'Drafts' },
    { key: 'trashed', label: 'Trash' },
];

const EMPTY_TEXT: Record<StoryFilter, string> = {
    all: 'No stories yet.',
    published: 'No published stories.',
    scheduled: 'Nothing is scheduled.',
    draft: 'No drafts.',
    trashed: 'The trash is empty.',
};

function DeleteForeverButton({ story }: { story: StoryRow }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    Delete permanently
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{story.title}” permanently?</DialogTitle>
                <DialogDescription>
                    This removes the story, its image and its old web addresses.
                    It cannot be undone.
                </DialogDescription>
                <Form
                    {...StoryController.forceDelete.form(story.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete permanently
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function StoriesIndex({ stories, filters, counts, can }: Props) {
    const tabHref = (status: StoryFilter) =>
        StoryController.index({
            query: {
                status: status === 'all' ? undefined : status,
                search: filters.search || undefined,
            },
        });

    return (
        <>
            <Head title="Stories" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Stories"
                        description="News and updates shown on the Canco Cares website."
                    />
                    <Button asChild>
                        <Link href={StoryController.create()}>
                            <Plus /> New story
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <nav
                        aria-label="Filter stories"
                        className="flex flex-wrap gap-1"
                    >
                        {TABS.map((tab) => {
                            const active = filters.status === tab.key;

                            return (
                                <Link
                                    key={tab.key}
                                    href={tabHref(tab.key)}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm transition-colors',
                                        active
                                            ? 'bg-secondary font-medium text-secondary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    {tab.label}{' '}
                                    <span className="text-xs tabular-nums opacity-70">
                                        {counts[tab.key]}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    <Form
                        {...StoryController.index.form()}
                        className="flex items-center gap-2"
                        role="search"
                    >
                        {filters.status !== 'all' && (
                            <input
                                type="hidden"
                                name="status"
                                value={filters.status}
                            />
                        )}
                        <Input
                            type="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search title or address"
                            aria-label="Search stories"
                            className="w-64"
                        />
                        <Button type="submit" variant="secondary">
                            <Search /> Search
                        </Button>
                    </Form>
                </div>

                {stories.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="text-muted-foreground">
                            {filters.search
                                ? `No stories match “${filters.search}”.`
                                : EMPTY_TEXT[filters.status]}
                        </p>
                        {filters.status === 'all' && !filters.search && (
                            <Button asChild className="mt-4">
                                <Link href={StoryController.create()}>
                                    <Plus /> Write the first story
                                </Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-16">
                                        <span className="sr-only">Image</span>
                                    </TableHead>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Publish date</TableHead>
                                    <TableHead>Last saved</TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stories.data.map((story) => (
                                    <TableRow key={story.id}>
                                        <TableCell>
                                            {story.image_url ? (
                                                <img
                                                    src={story.image_url}
                                                    alt=""
                                                    className="size-12 rounded object-cover"
                                                />
                                            ) : (
                                                <div
                                                    className="size-12 rounded bg-muted"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-md">
                                            {story.state === 'trashed' ? (
                                                <span className="font-medium">
                                                    {story.title}
                                                </span>
                                            ) : (
                                                <Link
                                                    href={StoryController.edit(
                                                        story.id,
                                                    )}
                                                    className="font-medium underline-offset-4 hover:underline"
                                                >
                                                    {story.title}
                                                </Link>
                                            )}
                                            <div className="truncate text-xs text-muted-foreground">
                                                /{story.slug}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <StoryStateBadge
                                                state={story.state}
                                            />
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(story.published_at)}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(story.updated_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {story.state === 'trashed' ? (
                                                <div className="flex justify-end gap-2">
                                                    <Form
                                                        {...StoryController.restore.form(
                                                            story.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Restore
                                                            </Button>
                                                        )}
                                                    </Form>
                                                    {can.forceDelete && (
                                                        <DeleteForeverButton
                                                            story={story}
                                                        />
                                                    )}
                                                </div>
                                            ) : (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={StoryController.edit(
                                                            story.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {stories.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Showing {stories.from}–{stories.to} of{' '}
                            {stories.total}
                        </span>
                        <div className="flex gap-2">
                            {stories.prev_page_url ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={stories.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            ) : null}
                            {stories.next_page_url ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={stories.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

StoriesIndex.layout = {
    breadcrumbs: [{ title: 'Stories', href: StoryController.index() }],
};
