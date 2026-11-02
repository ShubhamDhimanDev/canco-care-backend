import { ChevronDown } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: ReactNode;
    icon?: LucideIcon;
    /** Anchor for in-page navigation. */
    id?: string;
    /** Shown at the right of the header (a counter, a link). */
    aside?: ReactNode;
    /** When set, the section collapses; the value is whether it starts open. */
    collapsible?: boolean;
    defaultOpen?: boolean;
    className?: string;
    contentClassName?: string;
    children: ReactNode;
};

function Heading({
    title,
    description,
    icon: Icon,
}: Pick<Props, 'title' | 'description' | 'icon'>) {
    return (
        <span className="flex items-start gap-3 text-left">
            {Icon && (
                <span
                    className="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <Icon className="size-4.5" />
                </span>
            )}
            <span className="grid gap-1">
                <CardTitle className="text-base">{title}</CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </span>
        </span>
    );
}

/**
 * A form section: an icon, a title and a short explanation, then the fields. With `collapsible` the
 * header folds the section away (used for optional blocks such as search settings).
 */
export default function SectionCard({
    title,
    description,
    icon,
    id,
    aside,
    collapsible = false,
    defaultOpen = true,
    className,
    contentClassName,
    children,
}: Props) {
    if (collapsible) {
        return (
            <Collapsible defaultOpen={defaultOpen} asChild>
                <Card id={id} className={cn('scroll-mt-24', className)}>
                    <CardHeader>
                        <CollapsibleTrigger asChild>
                            <button
                                type="button"
                                className="group flex w-full items-center justify-between gap-3 rounded-md text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <Heading
                                    title={title}
                                    description={description}
                                    icon={icon}
                                />
                                <ChevronDown className="size-4 shrink-0 transition-transform group-data-[state=open]:rotate-180" />
                            </button>
                        </CollapsibleTrigger>
                    </CardHeader>
                    <CollapsibleContent>
                        <CardContent
                            className={cn('space-y-5', contentClassName)}
                        >
                            {children}
                        </CardContent>
                    </CollapsibleContent>
                </Card>
            </Collapsible>
        );
    }

    return (
        <Card id={id} className={cn('scroll-mt-24', className)}>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <Heading
                        title={title}
                        description={description}
                        icon={icon}
                    />
                    {aside}
                </div>
            </CardHeader>
            <CardContent className={cn('space-y-5', contentClassName)}>
                {children}
            </CardContent>
        </Card>
    );
}
