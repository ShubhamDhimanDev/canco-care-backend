import { Head, Link } from '@inertiajs/react';
import { KeyRound, Plus, ShieldCheck } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { UserRow } from '@/types';

export default function UsersIndex({ users }: { users: UserRow[] }) {
    return (
        <>
            <Head title="Users" />

            <div className="mx-auto w-full max-w-5xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Users"
                        description="People who can sign in to this admin. Give each one the roles they need, nothing more."
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={RoleController.index()}>
                                <KeyRound /> Roles
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={UserController.create()}>
                                <Plus /> New account
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Person</TableHead>
                                <TableHead>Access</TableHead>
                                <TableHead>Two-step sign-in</TableHead>
                                <TableHead>
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {user.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {user.email}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {user.is_super_admin ? (
                                                <Badge>
                                                    <ShieldCheck /> Super
                                                    administrator
                                                </Badge>
                                            ) : user.roles.length > 0 ? (
                                                user.roles.map((role) => (
                                                    <Badge
                                                        key={role}
                                                        variant="secondary"
                                                    >
                                                        {role}
                                                    </Badge>
                                                ))
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    No roles yet
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {user.two_factor ? 'On' : 'Off'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={UserController.edit(
                                                    user.id,
                                                )}
                                            >
                                                Edit
                                                <span className="sr-only">
                                                    {' '}
                                                    {user.name}
                                                </span>
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: UserController.index() }],
};
