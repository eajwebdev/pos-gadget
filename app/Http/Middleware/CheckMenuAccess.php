<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuAccess
{
    public function handle(Request $request, Closure $next, string $menuId): Response
    {
        $user = Auth::user();

        if (!$user) {
            // Not logged in → redirect to login
            return redirect()->route('login');
        }

        if (!$user->hasAccess($menuId)) {
            $home = $this->firstAccessibleRouteFor($user);

            if ($home !== null) {
                return redirect()->to($home)->with('error', 'You do not have access to that page.');
            }

            abort(403, 'Your account has no valid menu access assigned. Please contact the administrator.');
        }

        return $next($request);
    }

    private function firstAccessibleRouteFor(\App\Models\User $user): ?string
    {
        if ($user->isSuperAdmin() && Route::has('dashboard')) {
            return route('dashboard');
        }

        foreach ($this->menuLandingRoutes() as $menuId => $routeName) {
            if ($user->hasAccess($menuId) && Route::has($routeName)) {
                return route($routeName);
            }
        }

        return null;
    }

    private function menuLandingRoutes(): array
    {
        return [
            '1'  => 'dashboard',
            '2'  => 'pos.index',
            '3'  => 'sales.history',
            '5'  => 'shop.orders',
            '6'  => 'products.index',
            '12' => 'purchase-orders.index',
            '14' => 'cash-sessions.index',
            '15' => 'cash-counts.index',
            '16' => 'petty-cash.index',
            '17' => 'expenses.index',
            '18' => 'reports.daily',
            '19' => 'reports.sales',
            '20' => 'reports.inventory',
            '21' => 'reports.expenses',
            '22' => 'logs.index',
            '23' => 'users.index',
            '24' => 'suppliers.index',
            '25' => 'branches.index',
            '27' => 'expense-categories.index',
            '28' => 'settings.index',
            '29' => 'promos.index',
            '31' => 'stock-adjustments.index',
            '32' => 'installments.index',
            '33' => 'inventory.index',
            '34' => 'stock-transfers.index',
            '35' => 'warehouses.index',
            '36' => 'stock-count.index',
            '37' => 'brochure.index',
            '38' => 'services.index',
            '39' => 'customers.index',
            '40' => 'device-units.index',
            '41' => 'device-services.index',
        ];
    }
}
