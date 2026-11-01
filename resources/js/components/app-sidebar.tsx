import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    HandHeart,
    Home,
    ImageIcon,
    Inbox,
    LayoutGrid,
    ListTree,
    Newspaper,
    Settings,
} from 'lucide-react';
import DonationController from '@/actions/App/Http/Controllers/Admin/DonationController';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import HomePageController from '@/actions/App/Http/Controllers/Admin/HomePageController';
import InquiryController from '@/actions/App/Http/Controllers/Admin/InquiryController';
import InquiryTypeController from '@/actions/App/Http/Controllers/Admin/InquiryTypeController';
import MediaController from '@/actions/App/Http/Controllers/Admin/MediaController';
import SiteSettingsController from '@/actions/App/Http/Controllers/Admin/SiteSettingsController';
import StoryController from '@/actions/App/Http/Controllers/Admin/StoryController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const contentItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Stories',
        href: StoryController.index(),
        icon: Newspaper,
        matchPrefix: true,
    },
    {
        title: 'Events',
        href: EventController.index(),
        icon: CalendarDays,
        matchPrefix: true,
    },
    {
        title: 'Home page',
        href: HomePageController.edit(),
        icon: Home,
        matchPrefix: true,
    },
    {
        title: 'Media',
        href: MediaController.index(),
        icon: ImageIcon,
        matchPrefix: true,
    },
];

export function AppSidebar() {
    const { isAdmin, unreadInquiries } = usePage().props;

    const peopleItems: NavItem[] = [
        {
            title: 'Inquiries',
            href: InquiryController.index(),
            icon: Inbox,
            matchPrefix: true,
            badge: unreadInquiries,
        },
        // Donors' details are for administrators only.
        ...(isAdmin
            ? [
                  {
                      title: 'Donations',
                      href: DonationController.index(),
                      icon: HandHeart,
                      matchPrefix: true,
                  },
              ]
            : []),
    ];

    const settingsItems: NavItem[] = [
        {
            title: 'Site settings',
            href: SiteSettingsController.edit(),
            icon: Settings,
            matchPrefix: true,
        },
        {
            title: 'Inquiry topics',
            href: InquiryTypeController.index(),
            icon: ListTree,
            matchPrefix: true,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={contentItems} label="Website" />
                <NavMain items={peopleItems} label="People" />
                {isAdmin && <NavMain items={settingsItems} label="Settings" />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
