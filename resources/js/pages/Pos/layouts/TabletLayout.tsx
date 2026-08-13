import { ChevronDown, Layers, Package } from "lucide-react";
import { cn } from "@/lib/utils";
import { fmtMoney } from "../ReceiptTemplate";
import type { CartItem, Product } from "../posTypes";

export default function TabletLayout({ filtered, cart, currency, onProductClick }: {
    filtered: Product[];
    cart: CartItem[];
    currency: string;
    onProductClick: (p: Product) => void;
}) {
    if (filtered.length === 0) {
        return (
            <div className="flex h-64 flex-col items-center justify-center gap-2 text-muted-foreground">
                <Package className="h-9 w-9 opacity-20" />
                <p className="text-sm font-medium">No products found</p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            {filtered.map(product => {
                const inCart = cart.find(item => item.product_id === product.id);
                const isBundleMTO = product.product_type === "bundle" || product.product_type === "made_to_order";
                const outStock = !isBundleMTO && product.stock <= 0;
                const lowStock = !isBundleMTO && product.stock > 0 && product.stock <= 5;

                return (
                    <button
                        key={product.id}
                        onClick={() => onProductClick(product)}
                        disabled={outStock}
                        className={cn(
                            "relative flex min-h-[220px] touch-manipulation select-none flex-col overflow-hidden rounded-lg border text-left transition-all duration-150 active:scale-[0.98]",
                            outStock
                                ? "cursor-not-allowed border-border bg-card opacity-40"
                                : inCart
                                    ? "border-primary/70 bg-primary/5 shadow-sm ring-1 ring-primary/15"
                                    : "border-border bg-card hover:border-primary/40 hover:shadow-sm",
                        )}
                    >
                        <div className="w-full shrink-0 overflow-hidden bg-muted/40" style={{ aspectRatio: "5/3" }}>
                            {product.product_img ? (
                                <img src={product.product_img} alt={product.name} className="h-full w-full object-cover" loading="lazy" />
                            ) : (
                                <div className="flex h-full w-full items-center justify-center">
                                    <Package className="h-10 w-10 text-muted-foreground/20" />
                                </div>
                            )}
                        </div>

                        {inCart && (
                            <div className="absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-primary px-2 text-xs font-black text-primary-foreground shadow">
                                x{inCart.qty}
                            </div>
                        )}

                        {outStock && (
                            <div className="absolute inset-0 flex items-center justify-center bg-background/60">
                                <span className="rounded-full bg-background/90 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-destructive">
                                    Out of stock
                                </span>
                            </div>
                        )}

                        <div className="flex flex-1 flex-col p-3">
                            <p title={product.name} className="line-clamp-2 flex-1 text-base font-bold leading-snug text-foreground">
                                {product.name}
                            </p>
                            {product.category && <p className="mt-1 truncate text-xs text-muted-foreground">{product.category.name}</p>}

                            <div className="mt-3 flex items-end justify-between gap-2">
                                <span className="text-lg font-black tabular-nums text-primary">
                                    {fmtMoney(product.price, currency)}
                                </span>
                                <span className={cn(
                                    "shrink-0 rounded-md border border-border bg-background px-2 py-1 text-xs font-semibold",
                                    lowStock ? "text-amber-600 dark:text-amber-400" : "text-muted-foreground",
                                )}>
                                    {product.product_type === "made_to_order"
                                        ? "MTO"
                                        : product.product_type === "bundle"
                                            ? "Bundle"
                                            : lowStock
                                                ? `Low ${product.stock}`
                                                : `${product.stock} left`}
                                </span>
                            </div>

                            {product.has_variants && (
                                <p className="mt-1 flex items-center gap-1 text-[10px] text-muted-foreground">
                                    <ChevronDown className="h-2.5 w-2.5" />
                                    {product.variants.length} variants
                                </p>
                            )}

                            {product.product_type === "bundle" && product.bundle_items && (
                                <p className="mt-1 flex items-center gap-1 text-[10px] font-semibold text-violet-500">
                                    <Layers className="h-3 w-3" />
                                    {product.bundle_items.length} items in bundle
                                </p>
                            )}

                            {product.product_type === "made_to_order" && (
                                <p className="mt-1 text-[10px] font-semibold text-sky-500">Made to order</p>
                            )}
                        </div>
                    </button>
                );
            })}
        </div>
    );
}
