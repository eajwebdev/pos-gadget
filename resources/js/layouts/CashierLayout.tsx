"use client";

import { ReactNode, useEffect } from "react";
import { Link, Head, router, usePage } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { useTheme } from "next-themes";
import {
    ShoppingCart, History, Wallet, Calculator,
    PiggyBank, LogOut, Sun, Moon, CalendarClock,
} from "lucide-react";

// ─── Menu IDs (must match MenuHelper.php) ─────────────────────────────────────
const M = {
    POS:           "2",
    SALES_HISTORY: "3",
    CASH_SESSIONS: "14",
    CASH_COUNTS:   "15",
    PETTY_CASH:    "16",
    INSTALLMENTS:  "32",
} as const;

// Alt+1…6 — reliably interceptable, don't clash with browser or POS F-key bindings
const NAV = [
    { id: M.POS,           href: "/pos",           icon: ShoppingCart,  label: "Cashier",      key: "Alt+1" },
    { id: M.SALES_HISTORY, href: "/sales/history", icon: History,       label: "History",      key: "Alt+2" },
    { id: M.CASH_SESSIONS, href: "/cash-sessions", icon: Wallet,        label: "Cash Session", key: "Alt+3" },
    { id: M.CASH_COUNTS,   href: "/cash-counts",   icon: Calculator,    label: "Cash Count",   key: "Alt+4" },
    { id: M.PETTY_CASH,    href: "/petty-cash",    icon: PiggyBank,     label: "Petty Cash",   key: "Alt+5" },
    { id: M.INSTALLMENTS,  href: "/installments",  icon: CalendarClock, label: "Installments", key: "Alt+6" },
] as const;

export default function CashierLayout({ children }: { children: ReactNode }) {
    const { props } = usePage<any>();
    const { theme, setTheme } = useTheme();
    const currentPath = usePage().url.split("?")[0].replace(/\/$/, "");

    const access: string[] = props.auth?.user?.access ?? [];
    const has = (id: string) => access.includes(id);

    const user   = props.auth?.user;
    const branch = (props.branch as any) ?? user?.branch;
    const session = props.session as any;   // only present on POS page
    const appName = props.app?.name ?? "POS System";
    const appLogo = props.app?.logo_url ?? null;

    const isActive = (href: string) => {
        const h = href.replace(/\/$/, "");
        return currentPath === h || currentPath.startsWith(h + "/");
    };

    // Global nav shortcuts — disabled on /pos (it registers its own F-key handlers)
    useEffect(() => {
        if (currentPath === "/pos") return;
        const fn = (e: KeyboardEvent) => {
            if (!e.altKey) return;
            const digit = e.key; // "1"–"5" when altKey is held
            const item = NAV.find(n => n.key === `Alt+${digit}`);
            if (!item) return;
            // Always suppress the browser's Alt+key action first
            e.preventDefault();
            if (has(item.id)) router.visit(item.href);
        };
        window.addEventListener("keydown", fn);
        return () => window.removeEventListener("keydown", fn);
    }, [currentPath, access.join(",")]);

    const visibleNav = NAV.filter(n => has(n.id));

    return (
        <div className="flex flex-col h-screen overflow-hidden bg-background text-foreground">
            <Head title={(props as any).title ?? ""}>
                {appLogo && <link rel="icon" href={appLogo} />}
                {appLogo && <link rel="apple-touch-icon" href={appLogo} />}
            </Head>

            {/* ── Top bar (h-12) ───────────────────────────────────── */}
            <header className="shrink-0 h-12 bg-card border-b border-border flex items-center justify-between px-3 sm:px-4 gap-3">
                <div className="flex items-center gap-3 min-w-0">
                    <div className="shrink-0 h-8 w-8 flex items-center justify-center rounded-md bg-primary text-primary-foreground overflow-hidden">
                        {appLogo ? (
                            <img src={appLogo} alt={appName} className="h-full w-full object-contain bg-background" />
                        ) : (
                            <span className="font-bold text-xs">{appName.charAt(0).toUpperCase()}</span>
                        )}
                    </div>
                    <div className="min-w-0">
                        <span className="block truncate text-sm font-semibold leading-tight">
                            {branch?.name ?? appName}
                        </span>
                        <span className="hidden text-[11px] leading-tight text-muted-foreground sm:block">
                            {user?.fname} {user?.lname}
                        </span>
                    </div>
                    {/* Session status — only shown when POS passes it as a prop */}
                    {session !== undefined && (
                        <div className={cn(
                            "hidden md:flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0",
                            session
                                ? "bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400"
                                : "bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400"
                        )}>
                            <span className={cn("h-1.5 w-1.5 rounded-full",
                                session ? "bg-green-500" : "bg-amber-500")} />
                            {session ? "Session Open" : "No Session"}
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-2 shrink-0">
                    <Link
                        href="/cash-sessions"
                        className={cn(
                            "hidden h-7 items-center gap-1.5 rounded-md border px-2 text-[11px] font-semibold transition-colors sm:inline-flex",
                            session
                                ? "border-border text-muted-foreground hover:bg-muted hover:text-foreground"
                                : "border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300"
                        )}
                    >
                        <Wallet className="h-3.5 w-3.5" />
                        {session ? "Session" : "Open Session"}
                    </Link>
                    <Button
                        variant="ghost" size="icon" className="h-7 w-7"
                        onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
                        aria-label="Toggle theme"
                    >
                        <Sun className="h-3.5 w-3.5 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
                        <Moon className="absolute h-3.5 w-3.5 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
                    </Button>
                </div>
            </header>

            {/* ── Page content ─────────────────────────────────────── */}
            {/* POS page manages its own layout — no padding, no overflow */}
            <main className={cn("flex-1 min-h-0 overflow-hidden", currentPath !== "/pos" && "overflow-y-auto p-6")}>
                {children}
            </main>

            {/* ── Bottom nav bar (h-16) ────────────────────────────── */}
            <nav className="shrink-0 h-20 select-none border-t border-border bg-card/95 px-2 py-2 shadow-[0_-10px_30px_rgba(15,23,42,0.08)] backdrop-blur supports-[backdrop-filter]:bg-card/85 sm:px-3">
                <div className="flex h-full items-stretch gap-2 overflow-x-auto pb-0.5">
                    {visibleNav.map(item => {
                    const Icon = item.icon;
                    const active = isActive(item.href);
                    return (
                        <Link
                            key={item.id}
                            href={item.href}
                            className={cn(
                                "group relative flex min-w-[5.75rem] flex-1 flex-col items-center justify-center gap-1 rounded-lg border px-2 text-center shadow-sm transition-all duration-150 sm:min-w-[6.75rem]",
                                active
                                    ? "border-primary bg-primary text-primary-foreground shadow-md shadow-primary/20"
                                    : "border-border bg-background text-muted-foreground hover:-translate-y-0.5 hover:border-primary/40 hover:bg-primary/5 hover:text-foreground hover:shadow-md"
                            )}
                        >
                            <span className={cn(
                                "flex h-7 w-7 items-center justify-center rounded-md transition-colors",
                                active
                                    ? "bg-primary-foreground/15"
                                    : "bg-muted text-foreground group-hover:bg-background"
                            )}>
                                <Icon className="h-[18px] w-[18px]" />
                            </span>
                            <span className="max-w-full truncate text-[10px] font-bold leading-none sm:text-[11px]">{item.label}</span>
                            <span className={cn(
                                "absolute right-1.5 top-1.5 rounded px-1 py-px font-mono text-[8px] leading-none",
                                active
                                    ? "bg-primary-foreground/15 text-primary-foreground/80"
                                    : "bg-muted text-muted-foreground/70"
                            )}>
                                {item.key}
                            </span>
                        </Link>
                    );
                })}

                    <button
                        onClick={() => router.post("/logout", {}, { preserveState: false })}
                        className="group relative flex min-w-[5.5rem] shrink-0 flex-col items-center justify-center gap-1 rounded-lg border border-border bg-background px-2 text-center text-muted-foreground shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 hover:shadow-md dark:hover:border-rose-900/70 dark:hover:bg-rose-950/30 dark:hover:text-rose-300 sm:min-w-[6rem]"
                    >
                        <span className="flex h-7 w-7 items-center justify-center rounded-md bg-muted text-foreground transition-colors group-hover:bg-white dark:group-hover:bg-rose-950/60">
                            <LogOut className="h-[18px] w-[18px]" />
                        </span>
                        <span className="text-[10px] font-bold leading-none sm:text-[11px]">Logout</span>
                    </button>
                </div>
            </nav>
        </div>
    );
}
