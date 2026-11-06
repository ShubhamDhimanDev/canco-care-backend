import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    HandHeart,
    History,
    Home,
    ImageIcon,
    Inbox,
    LayoutGrid,
    KeyRound,
    ListTree,
    Newspaper,
    Settings,
    Users,
} from 'lucide-react';
import AuditLogController from '@/actions/App/Http/Controllers/Admin/AuditLogController';
import DonationController from '@/actions/App/Http/Controllers/Admin/DonationController';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import HomePageController from '@/actions/App/Http/Controllers/Admin/HomePageController';
import InquiryController from '@/actions/App/Http/Controllers/Admin/InquiryController';
import InquiryTypeController from '@/actions/App/Http/Controllers/Admin/InquiryTypeController';
import MediaController from '@/actions/App/Http/Controllers/Admin/MediaController';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import SiteSettingsController from '@/actions/App/Http/Controllers/Admin/SiteSettingsController';
import StoryController from '@/actions/App/Http/Controllers/Admin/StoryController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
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
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type GatedItem = NavItem & {
    /** Shown only to people holding at least one of these permissions. */
    needs?: string[];
};

export function AppSidebar() {
    const { unreadInquiries } = usePage().props;
    const { isAdmin, canAny } = usePermissions();

    const visible = (items: GatedItem[]): NavItem[] =>
        items
            .filter((item) => !item.needs || canAny(...item.needs))
            .map(({ needs: _needs, ...item }) => item);

    const contentItems = visible([
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
            needs: ['stories.view'],
        },
        {
            title: 'Events',
            href: EventController.index(),
            icon: CalendarDays,
            matchPrefix: true,
            needs: ['events.view'],
        },
        {
            title: 'Home page',
            href: HomePageController.edit(),
            icon: Home,
            matchPrefix: true,
            needs: ['home-page.update'],
        },
        {
            title: 'Media',
            href: MediaController.index(),
            icon: ImageIcon,
            matchPrefix: true,
            needs: ['media.view'],
        },
    ]);

    const peopleItems = visible([
        {
            title: 'Inquiries',
            href: InquiryController.index(),
            icon: Inbox,
            matchPrefix: true,
            badge: unreadInquiries,
            needs: ['inquiries.view'],
        },
        // Donors' details need their own permission.
        {
            title: 'Donations',
            href: DonationController.index(),
            icon: HandHeart,
            matchPrefix: true,
            needs: ['donations.view'],
        },
    ]);

    const settingsItems = visible([
        {
            title: 'Site settings',
            href: SiteSettingsController.edit(),
            icon: Settings,
            matchPrefix: true,
            needs: ['site-settings.manage'],
        },
        {
            title: 'Inquiry topics',
            href: InquiryTypeController.index(),
            icon: ListTree,
            matchPrefix: true,
            needs: ['inquiry-topics.manage'],
        },
    ]);

    // Managing sub-admins stays with super administrators.
    const accessItems: NavItem[] = isAdmin
        ? [
              {
                  title: 'Users',
                  href: UserController.index(),
                  icon: Users,
                  matchPrefix: true,
              },
              {
                  title: 'Roles',
                  href: RoleController.index(),
                  icon: KeyRound,
                  matchPrefix: true,
              },
              {
                  title: 'Activity log',
                  href: AuditLogController.index(),
                  icon: History,
                  matchPrefix: true,
              },
          ]
        : [];

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
                {peopleItems.length > 0 && (
                    <NavMain items={peopleItems} label="People" />
                )}
                {settingsItems.length > 0 && (
                    <NavMain items={settingsItems} label="Settings" />
                )}
                {accessItems.length > 0 && (
                    <NavMain items={accessItems} label="Access" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
