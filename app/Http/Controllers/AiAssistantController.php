<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\CustomerPayment;
use App\Models\DeviceUnit;
use App\Models\Expense;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Promo;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AiAssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate(['message' => ['required', 'string', 'max:500']]);

        /** @var User $user */
        $user = Auth::user();
        $branchId = $user->isSuperAdmin() ? null : $user->branch_id;
        $message = mb_strtolower(trim((string) $request->input('message')));
        $currency = SystemSetting::get('general.currency_symbol', $branchId, 'PHP ');

        if (! $user->isSuperAdmin() && ! $branchId) {
            return response()->json([
                'text' => 'EAJ Assistant needs your account to be assigned to a branch before it can read business data.',
            ]);
        }

        return response()->json($this->resolve($message, $branchId, $user, $currency));
    }

    private function resolve(string $message, ?int $branchId, User $user, string $currency): array
    {
        return match ($this->detect($message)) {
            'greeting' => $this->greeting($user),
            'help' => $this->help(),
            'sales_today' => $this->salesToday($branchId, $currency),
            'sales_yesterday' => $this->salesYesterday($branchId, $currency),
            'sales_week' => $this->salesWeek($branchId, $currency),
            'sales_month' => $this->salesMonth($branchId, $currency),
            'net_income' => $this->netIncome($branchId, $currency),
            'payment_mix' => $this->paymentMix($branchId, $currency),
            'low_stock' => $this->lowStock($branchId),
            'out_of_stock' => $this->outOfStock($branchId),
            'stock_summary' => $this->stockSummary($branchId),
            'top_products' => $this->topProducts($branchId, $currency, today()->startOfDay(), now()->endOfDay(), 'Top selling products today:'),
            'top_products_month' => $this->topProducts($branchId, $currency, now()->startOfMonth(), now()->endOfMonth(), 'Top selling products this month:'),
            'cash_session' => $this->cashSession($branchId, $currency),
            'expenses_today' => $this->expensesToday($branchId, $currency),
            'expenses_month' => $this->expensesMonth($branchId, $currency),
            'voids_today' => $this->voidsToday($branchId, $currency),
            'losses' => $this->losses($branchId, $currency),
            'pending_orders' => $this->pendingOrders($branchId, $currency),
            'recent_sales' => $this->recentSales($branchId, $currency),
            'discount_summary' => $this->discountSummary($branchId, $currency),
            'hourly_peak' => $this->hourlyPeak($branchId, $currency),
            'product_count' => $this->productCount($branchId),
            'device_units' => $this->deviceUnits($branchId),
            'promo_summary' => $this->promoSummary($branchId, $currency),
            'branch_summary' => $this->branchSummary($user, $currency),
            'installment_summary' => $this->installmentSummary($branchId, $currency),
            default => $this->unknown(),
        };
    }

    private function detect(string $message): string
    {
        $checks = [
            'greeting' => '/\b(hi|hello|hey|good\s*(morning|afternoon|evening)|kumusta|kamusta|musta)\b/',
            'help' => '/\b(help|commands?|what can you|guide|assist|ano.*alam|what.*ask)\b/',
            'device_units' => '/\b(device|devices|unit|units|imei|serial|serialized|warranty|phone.*unit|available.*unit|sold.*unit)\b/',
            'promo_summary' => '/\b(promos?|active.*promo|promo.*summary|coupon|voucher|deal|discount.*code)\b/',
            'voids_today' => '/\b(void|voided|cancel+ed|bawi|refund)\b/',
            'losses' => '/\b(loss|losses|damage[d]?|expired|theft|stolen|shrinkage|nawala|nasira|adjust+ment)\b/',
            'out_of_stock' => '/\b(out.of.stock|zero.?stock|wala.*stock|stockout|walang.*stock|zero)\b/',
            'low_stock' => '/\b(low.?stock|mababa.*stock|stock.*alert|reorder|almost.*out|kulang.*stock|warning.*stock)\b/',
            'stock_summary' => '/\b(stock.*summar|summar.*stock|stock.*status|stock.*health|overall.*stock|inventory.*summar)\b/',
            'pending_orders' => '/\b(pending.*order|purchase.*order|po\b|supplier.*order|order.*pending|pabili|inorder)\b/',
            'top_products_month' => '/\b(top.*month|best.*month|month.*best|month.*top|pinaka.*benta.*buwan|best.*sell.*month)\b/',
            'top_products' => '/\b(top|best.?sell|popular|most.*sold|bestsell|pinaka.*benta|best.*product|selling.*product)\b/',
            'payment_mix' => '/\b(payment.*method|method.*payment|gcash|cash.*card|card.*cash|payment.*mix|how.*paid|bayad)\b/',
            'discount_summary' => '/\b(discount|discounts|promo.*used|bawas|reduction|total.*discount)\b/',
            'hourly_peak' => '/\b(peak|busiest|rush.*hour|hour.*rush|peak.*hour|what.*hour|oras.*marami|hourly)\b/',
            'net_income' => '/\b(net|profit|income|kita.*ngayon|net.*income|kumikita|loss.*today)\b/',
            'cash_session' => '/\b(cash.?session|session|drawer|open.*session|kahon|cashier.*open)\b/',
            'recent_sales' => '/\b(recent|last.*sale|latest.*sale|last.*transaction|recent.*sale|recent.*transaction|last.*order)\b/',
            'expenses_month' => '/\b(expense.*month|month.*expense|gastos.*buwan|buwan.*gastos|monthly.*expense)\b/',
            'expenses_today' => '/\b(expense|expenses|gastos|spent|nagastos|cost.*today)\b/',
            'product_count' => '/\b(how many.*product|product.*count|total.*product|ilang.*product|product.*total|item.*count)\b/',
            'installment_summary' => '/\b(installment|instalment|layaway|downpayment|down.?payment|dp.*sale|financing|financed)\b/',
            'branch_summary' => '/\b(branch|branches|bawat.*branch|branch.*summar|all.*branch)\b/',
            'sales_week' => '/\b(week|weekly|this.*week|linggo|7.*day|past.*week)\b/',
            'sales_yesterday' => '/\b(yesterday|kahapon|last.*day|previous.*day)\b/',
            'sales_month' => '/\b(month|monthly|this.*month|buwan)\b/',
            'sales_today' => '/\b(sales?|revenue|earned|income|kita|benta|transaction|today|ngayon|how.*much)\b/',
        ];

        foreach ($checks as $intent => $pattern) {
            if (preg_match($pattern, $message)) {
                return $intent;
            }
        }

        return 'unknown';
    }

    private function fmt(float $amount, string $currency): string
    {
        return $currency.number_format($amount, 2);
    }

    private function collectedExpr(): string
    {
        return "SUM(CASE WHEN payment_method = 'installment' THEN amount_paid WHEN payment_method IN ('credit','mixed') THEN 0 ELSE total END)";
    }

    private function salesScope(?int $branchId): Builder
    {
        return Sale::completed()->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
    }

    private function customerPaymentsTotal(?int $branchId, string $from, string $to): float
    {
        return (float) CustomerPayment::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');
    }

    private function remittanceQuery(?int $branchId)
    {
        return InstallmentPayment::query()
            ->join('installment_plans', 'installment_payments.installment_plan_id', '=', 'installment_plans.id')
            ->when($branchId, fn ($query) => $query->where('installment_plans.branch_id', $branchId));
    }

    private function remittanceTotals(?int $branchId, string $from, string $to): array
    {
        $totals = ['cash' => 0.0, 'gcash' => 0.0, 'card' => 0.0, 'bank' => 0.0, 'total' => 0.0];

        $rows = $this->remittanceQuery($branchId)
            ->whereBetween('installment_payments.payment_date', [$from, $to])
            ->selectRaw('installment_payments.payment_method, SUM(installment_payments.amount) as total')
            ->groupBy('installment_payments.payment_method')
            ->get();

        foreach ($rows as $row) {
            $method = (string) $row->payment_method;
            if (array_key_exists($method, $totals)) {
                $totals[$method] = (float) $row->total;
            }
            $totals['total'] += (float) $row->total;
        }

        return $totals;
    }

    private function collectedForRange(?int $branchId, string $fromDate, string $toDate, $fromDateTime, $toDateTime): array
    {
        $data = $this->salesScope($branchId)
            ->whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->selectRaw('COUNT(*) as count, '.$this->collectedExpr().' as pos_collected, SUM(discount_amount) as discounts, AVG(total) as avg_txn')
            ->first();

        $posCollected = (float) ($data->pos_collected ?? 0);
        $creditPayments = $this->customerPaymentsTotal($branchId, $fromDate, $toDate);
        $remittances = $this->remittanceTotals($branchId, $fromDate, $toDate);

        return [
            'count' => (int) ($data->count ?? 0),
            'pos_collected' => $posCollected,
            'credit_payments' => $creditPayments,
            'remittances' => $remittances['total'],
            'total_collected' => $posCollected + $creditPayments + $remittances['total'],
            'discounts' => (float) ($data->discounts ?? 0),
            'avg_txn' => (float) ($data->avg_txn ?? 0),
        ];
    }

    private function greeting(User $user): array
    {
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return ['text' => "{$greeting}, {$user->fname}! I'm your EAJ business assistant. Ask me about sales, inventory, units, promos, expenses, cash, credit, or installments."];
    }

    private function help(): array
    {
        return [
            'text' => 'Here are the business checks I can run:',
            'items' => [
                ['label' => 'Sales today', 'value' => '"Sales today"'],
                ['label' => 'This week or month', 'value' => '"This week sales"'],
                ['label' => 'Net income', 'value' => '"Net income today"'],
                ['label' => 'Payment methods', 'value' => '"Payment mix today"'],
                ['label' => 'Serialized units', 'value' => '"Device units summary"'],
                ['label' => 'Promos', 'value' => '"Active promos"'],
                ['label' => 'Low or out of stock', 'value' => '"Show low stock"'],
                ['label' => 'Top products', 'value' => '"Best selling today"'],
                ['label' => 'Cash session', 'value' => '"Cash session status"'],
                ['label' => 'Credit and installments', 'value' => '"Installment summary"'],
                ['label' => 'Recent sales', 'value' => '"Recent transactions"'],
                ['label' => 'Branch summary', 'value' => '"Branch summary"'],
            ],
        ];
    }

    private function salesToday(?int $branchId, string $currency): array
    {
        $today = today()->toDateString();
        $totals = $this->collectedForRange($branchId, $today, $today, today()->startOfDay(), now()->endOfDay());

        if ($totals['count'] === 0 && $totals['total_collected'] <= 0) {
            return ['text' => 'No completed sales recorded yet today.'];
        }

        return [
            'text' => 'Sales summary for today:',
            'items' => $this->salesItems($totals, $currency),
        ];
    }

    private function salesYesterday(?int $branchId, string $currency): array
    {
        $day = now()->subDay();
        $date = $day->toDateString();
        $totals = $this->collectedForRange($branchId, $date, $date, $day->copy()->startOfDay(), $day->copy()->endOfDay());

        if ($totals['count'] === 0 && $totals['total_collected'] <= 0) {
            return ['text' => 'No completed sales were recorded yesterday.'];
        }

        return [
            'text' => 'Sales summary for yesterday:',
            'items' => $this->salesItems($totals, $currency),
        ];
    }

    private function salesWeek(?int $branchId, string $currency): array
    {
        $from = now()->startOfWeek();
        $to = now()->endOfWeek();
        $totals = $this->collectedForRange($branchId, $from->toDateString(), $to->toDateString(), $from, $to);
        $daysElapsed = $from->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1;
        $items = $this->salesItems($totals, $currency);
        $items[] = ['label' => 'Daily average', 'value' => $this->fmt($totals['total_collected'] / max(1, $daysElapsed), $currency)];

        return ['text' => 'Sales summary for this week:', 'items' => $items];
    }

    private function salesMonth(?int $branchId, string $currency): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $totals = $this->collectedForRange($branchId, $from->toDateString(), $to->toDateString(), $from, $to);
        $expenses = (float) Expense::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $items = $this->salesItems($totals, $currency);
        $items[] = ['label' => 'Expenses', 'value' => $this->fmt($expenses, $currency)];
        $items[] = ['label' => 'Net after expenses', 'value' => $this->fmt($totals['total_collected'] - $expenses, $currency)];

        return ['text' => 'Sales summary for '.now()->format('F Y').':', 'items' => $items];
    }

    private function salesItems(array $totals, string $currency): array
    {
        $items = [
            ['label' => 'Total collected', 'value' => $this->fmt($totals['total_collected'], $currency)],
            ['label' => 'POS sales collected', 'value' => $this->fmt($totals['pos_collected'], $currency)],
            ['label' => 'Transactions', 'value' => number_format($totals['count'])],
            ['label' => 'Average transaction', 'value' => $this->fmt($totals['avg_txn'], $currency)],
            ['label' => 'Discounts', 'value' => $this->fmt($totals['discounts'], $currency)],
        ];

        if ($totals['credit_payments'] > 0) {
            $items[] = ['label' => 'Customer credit payments', 'value' => $this->fmt($totals['credit_payments'], $currency)];
        }
        if ($totals['remittances'] > 0) {
            $items[] = ['label' => 'Installment remittances', 'value' => $this->fmt($totals['remittances'], $currency)];
        }

        return $items;
    }

    private function netIncome(?int $branchId, string $currency): array
    {
        $today = today()->toDateString();
        $totals = $this->collectedForRange($branchId, $today, $today, today()->startOfDay(), now()->endOfDay());
        $expenses = (float) Expense::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('expense_date', $today)
            ->sum('amount');
        $losses = (float) (StockAdjustment::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('created_at', '>=', today()->startOfDay())
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0);
        $net = $totals['total_collected'] - $expenses - $losses;

        return [
            'text' => 'Net income breakdown for today:',
            'items' => [
                ['label' => 'Total collected', 'value' => $this->fmt($totals['total_collected'], $currency)],
                ['label' => 'Expenses', 'value' => '- '.$this->fmt($expenses, $currency)],
                ['label' => 'Stock losses', 'value' => '- '.$this->fmt($losses, $currency)],
                ['label' => 'Net income', 'value' => $this->fmt($net, $currency), 'badge' => $net >= 0 ? 'Profit' : 'Loss'],
            ],
        ];
    }

    private function paymentMix(?int $branchId, string $currency): array
    {
        $rows = $this->salesScope($branchId)
            ->where('created_at', '>=', today()->startOfDay())
            ->selectRaw('payment_method, COUNT(*) as count, '.$this->collectedExpr().' as collected')
            ->groupBy('payment_method')
            ->orderByDesc('collected')
            ->get();

        $today = today()->toDateString();
        $creditPayments = $this->customerPaymentsTotal($branchId, $today, $today);
        $remittances = $this->remittanceTotals($branchId, $today, $today);
        $total = $rows->sum(fn ($row) => (float) $row->collected) + $creditPayments + $remittances['total'];

        if ($total <= 0) {
            return ['text' => 'No collected sales or payments yet today.'];
        }

        $items = $rows->map(fn ($row) => [
            'label' => ucfirst((string) $row->payment_method),
            'value' => $this->fmt((float) $row->collected, $currency),
            'badge' => $row->count.' txn',
        ])->toArray();

        if ($creditPayments > 0) {
            $items[] = ['label' => 'Customer credit payments', 'value' => $this->fmt($creditPayments, $currency)];
        }
        if ($remittances['total'] > 0) {
            $items[] = ['label' => 'Installment remittances', 'value' => $this->fmt($remittances['total'], $currency)];
        }
        $items[] = ['label' => 'Total collected', 'value' => $this->fmt($total, $currency), 'badge' => 'Total'];

        return ['text' => 'Payment method breakdown for today:', 'items' => $items];
    }

    private function lowStock(?int $branchId): array
    {
        $threshold = max(1, SystemSetting::lowStockThreshold($branchId));
        $rows = ProductStock::with('product:id,name')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('stock', '>', 0)
            ->where('stock', '<=', $threshold)
            ->orderBy('stock')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return ['text' => "No low-stock items right now. Threshold is {$threshold} units."];
        }

        return [
            'text' => "Low-stock items ({$threshold} or fewer units):",
            'items' => $rows->map(fn ($row) => [
                'label' => $row->product?->name ?? 'Unknown product',
                'value' => $row->stock.' units',
                'badge' => $row->stock <= 2 ? 'Critical' : 'Low',
            ])->toArray(),
        ];
    }

    private function outOfStock(?int $branchId): array
    {
        $rows = ProductStock::with('product:id,name')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('stock', '<=', 0)
            ->orderBy('updated_at')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return ['text' => 'No out-of-stock items found.'];
        }

        return [
            'text' => 'Out-of-stock items:',
            'items' => $rows->map(fn ($row) => [
                'label' => $row->product?->name ?? 'Unknown product',
                'value' => '0 units',
                'badge' => 'Out',
            ])->toArray(),
        ];
    }

    private function stockSummary(?int $branchId): array
    {
        $threshold = max(1, SystemSetting::lowStockThreshold($branchId));
        $base = ProductStock::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $inStock = (clone $base)->where('stock', '>', $threshold)->count();
        $lowStock = (clone $base)->where('stock', '>', 0)->where('stock', '<=', $threshold)->count();
        $outStock = (clone $base)->where('stock', '<=', 0)->count();

        return [
            'text' => 'Inventory health summary:',
            'items' => [
                ['label' => 'In stock', 'value' => number_format($inStock)],
                ['label' => 'Low stock', 'value' => number_format($lowStock), 'badge' => "At or below {$threshold}"],
                ['label' => 'Out of stock', 'value' => number_format($outStock)],
                ['label' => 'Total stock records', 'value' => number_format($inStock + $lowStock + $outStock)],
            ],
        ];
    }

    private function topProducts(?int $branchId, string $currency, $from, $to, string $title): array
    {
        $products = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->where('sale_items.is_bundle_component', false)
            ->when($branchId, fn ($query) => $query->where('sales.branch_id', $branchId))
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('products.name, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return ['text' => 'No product sales found for that period.'];
        }

        return [
            'text' => $title,
            'items' => $products->map(fn ($product) => [
                'label' => $product->name,
                'value' => $this->fmt((float) $product->revenue, $currency),
                'badge' => $product->qty_sold.' sold',
            ])->toArray(),
        ];
    }

    private function cashSession(?int $branchId, string $currency): array
    {
        $session = CashSession::with('user:id,fname,lname')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (! $session) {
            return ['text' => 'No active cash session is open right now.'];
        }

        $cashier = $session->user ? trim("{$session->user->fname} {$session->user->lname}") : 'Unknown';
        $remit = $this->remittanceTotals($session->branch_id, today()->toDateString(), today()->toDateString());

        return [
            'text' => 'Active cash session:',
            'items' => [
                ['label' => 'Cashier', 'value' => $cashier],
                ['label' => 'Opened at', 'value' => $session->opened_at?->format('g:i A') ?? '-'],
                ['label' => 'Opening cash', 'value' => $this->fmt((float) $session->opening_cash, $currency)],
                ['label' => 'Cash sales', 'value' => $this->fmt($session->cash_sales_total, $currency)],
                ['label' => 'Installment DP', 'value' => $this->fmt($session->installment_dp_total, $currency)],
                ['label' => 'Cash remittances', 'value' => $this->fmt($remit['cash'], $currency)],
                ['label' => 'Petty cash out', 'value' => '- '.$this->fmt($session->petty_cash_paid, $currency)],
                ['label' => 'Expected drawer', 'value' => $this->fmt($session->computeExpectedCash() + $remit['cash'], $currency), 'badge' => 'Cash only'],
            ],
        ];
    }

    private function expensesToday(?int $branchId, string $currency): array
    {
        return $this->expenseSummary($branchId, $currency, today()->toDateString(), today()->toDateString(), "Today's expenses:");
    }

    private function expensesMonth(?int $branchId, string $currency): array
    {
        return $this->expenseSummary($branchId, $currency, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), 'Monthly expenses:');
    }

    private function expenseSummary(?int $branchId, string $currency, string $from, string $to, string $title): array
    {
        $rows = Expense::with('category:id,name')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->orderByDesc('total')
            ->get();

        if ($rows->isEmpty()) {
            return ['text' => 'No expenses recorded for that period.'];
        }

        $items = $rows->map(fn ($row) => [
            'label' => $row->category?->name ?? 'Uncategorized',
            'value' => $this->fmt((float) $row->total, $currency),
        ])->toArray();
        $items[] = ['label' => 'Total', 'value' => $this->fmt($rows->sum(fn ($row) => (float) $row->total), $currency), 'badge' => 'Total'];

        return ['text' => $title, 'items' => $items];
    }

    private function voidsToday(?int $branchId, string $currency): array
    {
        $data = Sale::voided()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('created_at', '>=', today()->startOfDay())
            ->selectRaw('COUNT(*) as count, SUM(total) as total')
            ->first();

        $count = (int) ($data->count ?? 0);
        if ($count === 0) {
            return ['text' => 'No voided transactions today.'];
        }

        return [
            'text' => 'Voided transactions today:',
            'items' => [
                ['label' => 'Void count', 'value' => number_format($count)],
                ['label' => 'Voided value', 'value' => $this->fmt((float) ($data->total ?? 0), $currency)],
            ],
        ];
    }

    private function losses(?int $branchId, string $currency): array
    {
        $rows = StockAdjustment::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('type, SUM(quantity) as total_qty, SUM(quantity * unit_cost) as total_cost')
            ->groupBy('type')
            ->get();

        if ($rows->isEmpty()) {
            return ['text' => 'No stock adjustments recorded this month.'];
        }

        $items = $rows->map(fn ($row) => [
            'label' => ucfirst((string) $row->type),
            'value' => $this->fmt((float) $row->total_cost, $currency),
            'badge' => $row->total_qty.' units',
        ])->toArray();
        $items[] = ['label' => 'Total loss value', 'value' => $this->fmt($rows->sum(fn ($row) => (float) $row->total_cost), $currency)];

        return ['text' => 'Stock loss summary for this month:', 'items' => $items];
    }

    private function pendingOrders(?int $branchId, string $currency): array
    {
        $orders = Order::with('supplier:id,name')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['pending', 'confirmed', 'shipped'])
            ->latest()
            ->limit(6)
            ->get();

        if ($orders->isEmpty()) {
            return ['text' => 'No pending purchase orders right now.'];
        }

        $items = $orders->map(fn ($order) => [
            'label' => $order->order_number.' - '.($order->supplier?->name ?? 'No supplier'),
            'value' => $this->fmt((float) $order->total, $currency),
            'badge' => ucfirst((string) $order->status),
        ])->toArray();

        return ['text' => 'Pending purchase orders:', 'items' => $items];
    }

    private function recentSales(?int $branchId, string $currency): array
    {
        $sales = Sale::with('user:id,fname,lname')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest()
            ->limit(6)
            ->get();

        if ($sales->isEmpty()) {
            return ['text' => 'No transactions found yet.'];
        }

        return [
            'text' => 'Recent transactions:',
            'items' => $sales->map(fn (Sale $sale) => [
                'label' => ($sale->receipt_number ?? 'No receipt').' - '.$sale->created_at?->format('M d, g:i A'),
                'value' => $sale->payment_method === 'installment'
                    ? $this->fmt((float) $sale->amount_paid, $currency)
                    : $this->fmt((float) $sale->total, $currency),
                'badge' => ucfirst((string) $sale->payment_method).' - '.ucfirst((string) $sale->status),
            ])->toArray(),
        ];
    }

    private function discountSummary(?int $branchId, string $currency): array
    {
        $data = $this->salesScope($branchId)
            ->where('created_at', '>=', today()->startOfDay())
            ->selectRaw('COUNT(CASE WHEN discount_amount > 0 THEN 1 END) as discounted_count, SUM(discount_amount) as discount_total, SUM(total + discount_amount) as before_discount')
            ->first();

        $discount = (float) ($data->discount_total ?? 0);
        if ($discount <= 0) {
            return ['text' => 'No discounts have been applied today.'];
        }

        $beforeDiscount = (float) ($data->before_discount ?? 0);

        return [
            'text' => 'Discount summary for today:',
            'items' => [
                ['label' => 'Discounted transactions', 'value' => number_format((int) ($data->discounted_count ?? 0))],
                ['label' => 'Discount value', 'value' => $this->fmt($discount, $currency)],
                ['label' => 'Discount rate', 'value' => ($beforeDiscount > 0 ? round($discount / $beforeDiscount * 100, 2) : 0).'%'],
            ],
        ];
    }

    private function hourlyPeak(?int $branchId, string $currency): array
    {
        $sales = $this->salesScope($branchId)
            ->where('created_at', '>=', today()->startOfDay())
            ->get(['created_at', 'total']);

        if ($sales->isEmpty()) {
            return ['text' => 'No sales data today to determine peak hours yet.'];
        }

        $rows = $sales
            ->groupBy(fn (Sale $sale) => (int) $sale->created_at->format('G'))
            ->map(fn ($group, int $hour) => (object) [
                'hour' => $hour,
                'count' => $group->count(),
                'revenue' => $group->sum(fn (Sale $sale) => (float) $sale->total),
            ])
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        $formatHour = fn (int $hour) => ($hour === 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour)).($hour < 12 ? 'am' : 'pm');
        $peak = $rows->first();

        return [
            'text' => "Busiest hours today by sales value (peak: {$formatHour($peak->hour)}):",
            'items' => $rows->map(fn ($row) => [
                'label' => $formatHour($row->hour),
                'value' => $this->fmt((float) $row->revenue, $currency),
                'badge' => $row->count.' txn',
            ])->toArray(),
        ];
    }

    private function productCount(?int $branchId): array
    {
        $threshold = max(1, SystemSetting::lowStockThreshold($branchId));
        $total = Product::where('product_type', '!=', 'ingredient')
            ->when($branchId, fn ($query) => $query->whereHas('stocks', fn ($stock) => $stock->where('branch_id', $branchId)))
            ->count();
        $stockBase = ProductStock::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        return [
            'text' => 'Product and inventory count:',
            'items' => [
                ['label' => 'Products in scope', 'value' => number_format($total)],
                ['label' => 'Stock records with units', 'value' => number_format((clone $stockBase)->where('stock', '>', 0)->count())],
                ['label' => 'Low stock', 'value' => number_format((clone $stockBase)->where('stock', '>', 0)->where('stock', '<=', $threshold)->count())],
                ['label' => 'Out of stock', 'value' => number_format((clone $stockBase)->where('stock', '<=', 0)->count())],
            ],
        ];
    }

    private function deviceUnits(?int $branchId): array
    {
        if (! Schema::hasTable('device_units')) {
            return ['text' => 'Serialized unit tracking is not ready. Run the latest database migrations first.'];
        }

        $base = DeviceUnit::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $total = (clone $base)->count();
        if ($total === 0) {
            return ['text' => 'No serialized units found for this scope.'];
        }

        $recent = (clone $base)->with('product:id,name')->latest()->limit(5)->get();
        $items = [
            ['label' => 'Total units', 'value' => number_format($total)],
            ['label' => 'Available', 'value' => number_format((clone $base)->where('status', 'available')->count())],
            ['label' => 'Sold', 'value' => number_format((clone $base)->where('status', 'sold')->count())],
            ['label' => 'In service', 'value' => number_format((clone $base)->where('status', 'in_service')->count())],
            ['label' => 'Active warranty', 'value' => number_format((clone $base)->where('status', 'sold')->whereDate('warranty_expires_at', '>=', today())->count())],
        ];

        foreach ($recent as $unit) {
            $items[] = [
                'label' => ($unit->product?->name ?? 'Unknown product').' - '.$unit->identifier,
                'value' => ucfirst((string) $unit->status),
                'badge' => 'Recent',
            ];
        }

        return ['text' => 'Serialized unit summary:', 'items' => $items];
    }

    private function promoSummary(?int $branchId, string $currency): array
    {
        if (! Promo::tableExists()) {
            return ['text' => 'Promos are not ready. Run the latest database migrations first.'];
        }

        $total = Promo::count();
        if ($total === 0) {
            return ['text' => 'No promos have been created yet.'];
        }

        $discountsToday = $this->salesScope($branchId)
            ->where('created_at', '>=', today()->startOfDay())
            ->sum('discount_amount');

        return [
            'text' => 'Promo and discount summary:',
            'items' => [
                ['label' => 'Total promos', 'value' => number_format($total)],
                ['label' => 'Active now', 'value' => number_format(Promo::active()->count())],
                ['label' => 'Scheduled', 'value' => number_format(Promo::where('is_active', true)->whereNotNull('starts_at')->where('starts_at', '>', now())->count())],
                ['label' => 'Expired', 'value' => number_format(Promo::whereNotNull('expires_at')->where('expires_at', '<', now())->count())],
                ['label' => 'Recorded promo uses', 'value' => number_format((int) Promo::sum('uses_count'))],
                ['label' => 'Discounts today', 'value' => $this->fmt((float) $discountsToday, $currency)],
            ],
        ];
    }

    private function branchSummary(User $user, string $currency): array
    {
        $branches = $user->isSuperAdmin()
            ? Branch::where('is_active', true)->get()
            : Branch::whereKey($user->branch_id)->get();

        if ($branches->isEmpty()) {
            return ['text' => 'No active branch found for this account.'];
        }

        $today = today()->toDateString();
        $items = $branches->map(function (Branch $branch) use ($currency, $today) {
            $totals = $this->collectedForRange($branch->id, $today, $today, today()->startOfDay(), now()->endOfDay());

            return [
                'label' => $branch->name,
                'value' => $this->fmt($totals['total_collected'], $currency),
                'badge' => $totals['count'].' txn',
            ];
        })->toArray();

        return ['text' => $user->isSuperAdmin() ? 'Branch sales summary for today:' : 'Your branch summary for today:', 'items' => $items];
    }

    private function installmentSummary(?int $branchId, string $currency): array
    {
        $todaySales = $this->salesScope($branchId)
            ->where('payment_method', 'installment')
            ->where('created_at', '>=', today()->startOfDay())
            ->selectRaw('COUNT(*) as count, SUM(amount_paid) as dp_total, SUM(total) as financed_total')
            ->first();
        $monthSales = $this->salesScope($branchId)
            ->where('payment_method', 'installment')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('COUNT(*) as count, SUM(amount_paid) as dp_total, SUM(total) as financed_total')
            ->first();
        $todayRemit = $this->remittanceTotals($branchId, today()->toDateString(), today()->toDateString());
        $monthRemit = $this->remittanceTotals($branchId, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $activePlans = InstallmentPlan::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as count, SUM(balance - total_paid) as outstanding')
            ->first();

        if ((int) ($monthSales->count ?? 0) === 0 && $monthRemit['total'] <= 0 && (int) ($activePlans->count ?? 0) === 0) {
            return ['text' => 'No installment activity recorded this month.'];
        }

        return [
            'text' => 'Installment and remittance summary:',
            'items' => [
                ['label' => 'Today DP at POS', 'value' => $this->fmt((float) ($todaySales->dp_total ?? 0), $currency), 'badge' => ((int) ($todaySales->count ?? 0)).' new'],
                ['label' => 'Today remittances', 'value' => $this->fmt($todayRemit['total'], $currency)],
                ['label' => 'Month DP at POS', 'value' => $this->fmt((float) ($monthSales->dp_total ?? 0), $currency), 'badge' => ((int) ($monthSales->count ?? 0)).' sales'],
                ['label' => 'Month financed value', 'value' => $this->fmt((float) ($monthSales->financed_total ?? 0), $currency)],
                ['label' => 'Month remittances', 'value' => $this->fmt($monthRemit['total'], $currency)],
                ['label' => 'Active plans', 'value' => number_format((int) ($activePlans->count ?? 0))],
                ['label' => 'Outstanding balance', 'value' => $this->fmt((float) ($activePlans->outstanding ?? 0), $currency)],
            ],
        ];
    }

    private function unknown(): array
    {
        return [
            'text' => 'I did not understand that request yet. Try "sales today", "device units", "active promos", "low stock", "payment mix", "cash session", "installment summary", or "help".',
        ];
    }
}
