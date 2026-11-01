import { Form, Head } from '@inertiajs/react';
import StoryController from '@/actions/App/Http/Controllers/Admin/StoryController';
import StoryForm from '@/components/stories/story-form';
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
import { formatDateTime } from '@/lib/date';
import type { StoryFormData } from '@/types';

export default function EditStory({ story }: { story: StoryFormData }) {
    return (
        <>
            <Head title={`Edit: ${story.title}`} />

            <div className="mx-auto w-full max-w-6xl space-y-8 p-4">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Edit story
                        </h2>
                        <StoryStateBadge state={story.state} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Last saved {formatDateTime(story.updated_at)}
                    </p>
                </div>

                <StoryForm
                    form={StoryController.update.form(story.id)}
                    story={story}
                    submitLabel="Save changes"
                />

                <div className="rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                    <p className="font-medium text-red-700 dark:text-red-100">
                        Move to trash
                    </p>
                    <p className="mb-3 text-sm text-red-600 dark:text-red-100/80">
                        The story disappears from the website. You can restore
                        it from the trash.
                    </p>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="destructive"
                                data-test="trash-story-button"
                            >
                                Move to trash
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                Move “{story.title}” to the trash?
                            </DialogTitle>
                            <DialogDescription>
                                It will no longer appear on the website. You can
                                restore it later from the Trash filter.
                            </DialogDescription>

                            <Form
                                {...StoryController.destroy.form(story.id)}
                                className="space-y-6"
                            >
                                {({ processing }) => (
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            type="submit"
                                            disabled={processing}
                                            data-test="confirm-trash-story-button"
                                        >
                                            Move to trash
                                        </Button>
                                    </DialogFooter>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}

EditStory.layout = {
    breadcrumbs: [
        { title: 'Stories', href: StoryController.index() },
        { title: 'Edit story', href: StoryController.index() },
    ],
};
