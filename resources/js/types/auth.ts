export type UserType = 'staff' | 'buyer' | 'seller';

export type User = {
    ulid: string;
    type: UserType;
    first_name: string;
    last_name: string;
    /** First and last name combined. */
    name: string;
    mobile: string | null;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** Permission names of the signed-in staff member; empty for buyers. */
    permissions: string[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
