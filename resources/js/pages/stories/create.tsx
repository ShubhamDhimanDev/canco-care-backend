import { Head } from '@inertiajs/react';
import StoryController from '@/actions/App/Http/Controllers/Admin/StoryController';
import StoryForm from '@/components/stories/story-form';

export default function CreateStory({
    allTags,
    siteUrl,
}: {
    allTags: string[];
    siteUrl: string;
}) {
    return (
        <>
            <Head title="New story" />

            <div className="mx-auto w-full max-w-6xl p-4">
                <StoryForm
                    form={StoryController.store.form()}
                    title="New story"
                    subtitle="Save it as a draft first, or publish it right away."
                    submitLabel="Create story"
                    allTags={allTags}
                    siteUrl={siteUrl}
                />
            </div>
        </>
    );
}

CreateStory.layout = {
    breadcrumbs: [
        { title: 'Stories', href: StoryController.index() },
        { title: 'New story', href: StoryController.create() },
    ],
};
