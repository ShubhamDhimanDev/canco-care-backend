/** One account on the Users screen. */
export type UserRow = {
    id: number;
    name: string;
    email: string;
    is_super_admin: boolean;
    /** Names of the roles this person holds. */
    roles: string[];
    two_factor: boolean;
    created_at: string | null;
    /** Only on the edit screen. */
    role_ids?: number[];
};

/** A role as offered in the user form. */
export type RoleOption = {
    id: number;
    name: string;
    permissions_count: number;
};

export type RoleRow = {
    id: number;
    name: string;
    users_count: number;
    permissions_count: number;
};

export type PermissionGroup = {
    key: string;
    label: string;
    description: string;
    permissions: { name: string; label: string }[];
};

export type RoleFormData = {
    id: number | null;
    name: string;
    permissions: string[];
    users_count: number;
};
