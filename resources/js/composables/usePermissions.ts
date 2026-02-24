import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface AuthPermissions {
    permissions: string[];
}

export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(() => {
        return (page.props.auth as unknown as AuthPermissions)?.permissions ?? [];
    });

    const roles = computed<Array<{ name: string; display_name: string; color: string }>>(() => {
        return (page.props.auth as any)?.user?.roles ?? [];
    });

    /**
     * Cek apakah user punya permission tertentu
     * @param permission - nama permission, e.g. 'stock.edit'
     */
    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    /**
     * Cek apakah user punya semua permission yang diberikan
     * @param perms - array permission names
     */
    function canAll(...perms: string[]): boolean {
        return perms.every(p => permissions.value.includes(p));
    }

    /**
     * Cek apakah user punya salah satu dari permissions
     * @param perms - array permission names
     */
    function canAny(...perms: string[]): boolean {
        return perms.some(p => permissions.value.includes(p));
    }

    /**
     * Cek apakah user punya role tertentu
     * @param roleName - nama role, e.g. 'admin'
     */
    function hasRole(roleName: string): boolean {
        return roles.value.some(r => r.name === roleName);
    }

    return { can, canAll, canAny, hasRole, permissions, roles };
}
