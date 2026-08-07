import { Check, ChevronsUpDown } from "lucide-react";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";

export type SearchableProductOption = {
    id: number | string;
    name: string;
    barcode?: string | null;
    sku?: string | null;
    product_type?: string | null;
    category?: { name?: string | null } | string | null;
    warranty_months?: number | null;
};

type Props<T extends SearchableProductOption> = {
    products: T[];
    value: string;
    onChange: (value: string, product?: T) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    className?: string;
    disabled?: boolean;
};

export default function SearchableProductSelect<T extends SearchableProductOption>({
    products,
    value,
    onChange,
    placeholder = "Select product…",
    searchPlaceholder = "Search product, barcode, or SKU…",
    emptyText = "No products found.",
    className,
    disabled = false,
}: Props<T>) {
    const [open, setOpen] = useState(false);
    const selected = useMemo(
        () => products.find(product => String(product.id) === value),
        [products, value],
    );

    const optionMeta = (product: SearchableProductOption) => {
        const category = typeof product.category === "string" ? product.category : product.category?.name;

        return [
            product.barcode,
            product.sku,
            product.product_type?.replace("_", " "),
            category,
        ].filter(Boolean).join(" • ");
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn(
                        "h-10 w-full justify-between rounded-lg border-border bg-background px-3 text-left font-normal",
                        !selected && "text-muted-foreground",
                        className,
                    )}
                >
                    <span className="truncate">
                        {selected ? selected.name : placeholder}
                    </span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="z-[80] w-[var(--radix-popover-trigger-width)] p-0" align="start">
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {products.map(product => {
                                const productValue = String(product.id);
                                const meta = optionMeta(product);

                                return (
                                    <CommandItem
                                        key={productValue}
                                        value={`${product.name} ${product.barcode ?? ""} ${product.sku ?? ""} ${product.product_type ?? ""}`}
                                        onSelect={() => {
                                            onChange(productValue, product);
                                            setOpen(false);
                                        }}
                                    >
                                        <Check className={cn("h-4 w-4", value === productValue ? "opacity-100" : "opacity-0")} />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{product.name}</p>
                                            {meta && <p className="truncate text-xs text-muted-foreground">{meta}</p>}
                                        </div>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
