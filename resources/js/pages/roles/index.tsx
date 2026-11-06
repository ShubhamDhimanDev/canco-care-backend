import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { RoleRow } from '@/types';

type Props = { roles: RoleRow[]; totalPermissions: number };

function DeleteRole({ role }: { role: RoleRow }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive-foreground"
                >
                    Delete
                    <span className="sr-only"> {role.name}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete the “{role.name}” role?</DialogTitle>
                <DialogDescription>
                    {role.users_count === 0
                        ? 'Nobody has this role.'
                        : `${role.users_count} ${role.users_count === 1 ? 'person' : 'people'} will lose the access it gives.`}{' '}
                    This cannot be undone.
                </DialogDescription>
                <Form
                    {...RoleController.destroy.form(role.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete role
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function RolesIndex({ roles, totalPermissions }: Props) {
    return (
        <>
            <Head title="Roles" />

            <div className="mx-auto w-full max-w-4xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Roles"
                        description="A role is a named set of permissions, such as “Content writer” or “Inbox manager”. Give people roles from the Users screen."
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={UserController.index()}>
                                <Users /> Users
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={RoleController.create()}>
                                <Plus /> New role
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead>People</TableHead>
                                <TableHead>
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No roles yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell className="font-medium">
                                        {role.name}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {role.permissions_count} of{' '}
                                        {totalPermissions}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {role.users_count}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={RoleController.edit(
                                                    role.id,
                                                )}
                                            >
                                                Edit
                                                <span className="sr-only">
                                                    {' '}
                                                    {role.name}
                                                </span>
                                            </Link>
                                        </Button>
                                        <DeleteRole role={role} />
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

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: RoleController.index() }],
};
