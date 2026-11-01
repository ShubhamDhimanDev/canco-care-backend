import { Head } from '@inertiajs/react';
import StoryController from '@/actions/App/Http/Controllers/Admin/StoryController';
import Heading from '@/components/heading';
import StoryForm from '@/components/stories/story-form';

export default function CreateStory() {
    return (
        <>
            <Head title="New story" />

            <div className="mx-auto w-full max-w-6xl p-4">
                <Heading
                    title="New story"
                    description="Save it as a draft first, or publish it right away."
                />
                <StoryForm
                    form={StoryController.store.form()}
                    submitLabel="Create story"
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
