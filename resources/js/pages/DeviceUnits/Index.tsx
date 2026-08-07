import { Head, router, useForm } from "@inertiajs/react";
import { Barcode, Boxes, Check, Layers3, Plus, Printer, Search, ShieldCheck, Smartphone, Trash2, Truck, X } from "lucide-react";
import type { FormEvent } from "react";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import SearchableProductSelect from "@/components/SearchableProductSelect";
import AdminLayout from "@/layouts/AdminLayout";

type Option = { id: number; name: string; barcode?: string | null; category_id?: number | null; warranty_months?: number };
type Category = { id: number; name: string };
type Unit = {
    id: number;
    product_id: number;
    product_name: string;
    product_category_id: number | null;
    branch_id: number;
    branch_name: string;
    supplier_id: number | null;
    supplier_name: string | null;
    imei: string | null;
    imei_2: string | null;
    serial_number: string | null;
    identifier: string;
    status: string;
    cost: number | null;
    acquired_at: string | null;
    sold_at: string | null;
    warranty_months: number;
    warranty_expires_at: string | null;
    warranty_status: string;
    receipt_number: string | null;
    customer_name: string | null;
    notes: string | null;
};
type PageProps = {
    units: { data: Unit[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    tagUnits: Unit[];
    stats: Record<string, number>;
    products: Option[];
    categories: Category[];
    branches: Option[];
    suppliers: Option[];
    filters: { search?: string; status?: string; branch_id?: string };
    isSuperAdmin: boolean;
};

const input = "h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-1 focus:ring-primary";
const compactInput = "h-9 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:ring-1 focus:ring-primary";
const statusStyle: Record<string, string> = { available: "bg-emerald-500/15 text-emerald-500", sold: "bg-blue-500/15 text-blue-500", in_service: "bg-amber-500/15 text-amber-500", damaged: "bg-red-500/15 text-red-500", returned: "bg-purple-500/15 text-purple-500" };

const CODE128_PATTERNS = [
    "212222","222122","222221","121223","121322","131222","122213","122312","132212","221213","221312","231212","112232","122132","122231","113222","123122","123221","223211","221132","221231","213212","223112","312131","311222","321122","321221","312212","322112","322211","212123","212321","232121","111323","131123","131321","112313","132113","132311","211313","231113","231311","112133","112331","132131","113123","113321","133121","313121","211331","231131","213113","213311","213131","311123","311321","331121","312113","312311","332111","314111","221411","431111","111224","111422","121124","121421","141122","141221","112214","112412","122114","122411","142112","142211","241211","221114","413111","241112","134111","111242","121142","121241","114212","124112","124211","411212","421112","421211","212141","214121","412121","111143","111341","131141","114113","114311","411113","411311","113141","114131","311141","411131","211412","211214","211232","2331112",
];

function tagValue(unit: Unit): string {
    return unit.imei || unit.imei_2 || unit.serial_number || "";
}

function tagLabel(unit: Unit): string {
    if (unit.imei) return "IMEI";
    if (unit.imei_2) return "IMEI 2";
    return "SERIAL";
}

function chunkArray<T>(items: T[], size: number): T[][] {
    const chunks: T[][] = [];
    for (let i = 0; i < items.length; i += size) chunks.push(items.slice(i, i + size));
    return chunks;
}

function Code128Barcode({ value, className = "h-11 w-full" }: { value: string; className?: string }) {
    const safe = value.replace(/[^\x20-\x7E]/g, "");
    if (!safe) return null;

    const dataCodes = Array.from(safe).map(ch => ch.charCodeAt(0) - 32);
    const checksum = dataCodes.reduce((sum, code, index) => sum + code * (index + 1), 104) % 103;
    const codes = [104, ...dataCodes, checksum, 106];
    const quiet = 10;
    const height = 42;
    const width = codes.reduce((sum, code) => sum + CODE128_PATTERNS[code].split("").reduce((s, n) => s + Number(n), 0), quiet * 2);
    let x = quiet;

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className={className} preserveAspectRatio="none" aria-label={`Barcode ${safe}`}>
            <rect width={width} height={height} fill="#fff" />
            {codes.flatMap((code, codeIndex) => {
                const pattern = CODE128_PATTERNS[code];
                return pattern.split("").map((raw, index) => {
                    const barWidth = Number(raw);
                    const currentX = x;
                    x += barWidth;
                    return index % 2 === 0
                        ? <rect key={`${codeIndex}-${index}`} x={currentX} y={0} width={barWidth} height={height} fill="#000" />
                        : null;
                });
            })}
        </svg>
    );
}

function Modal({ title, onClose, children, maxWidth = "max-w-xl" }: { title: string; onClose: () => void; children: React.ReactNode; maxWidth?: string }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onMouseDown={onClose}>
            <div className={`max-h-[92vh] w-full ${maxWidth} overflow-y-auto rounded-2xl border border-border bg-card shadow-2xl`} onMouseDown={e => e.stopPropagation()}>
                <div className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-card/95 p-4 backdrop-blur">
                    <h2 className="font-bold">{title}</h2>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"><X className="h-4 w-4" /></button>
                </div>
                {children}
            </div>
        </div>
    );
}

function ImeiTag({ unit, printClass = "" }: { unit: Unit; printClass?: string }) {
    const value = tagValue(unit);

    return (
        <div className={`imei-phone-tag h-[17mm] w-[37mm] overflow-hidden rounded-[3px] border border-zinc-950 bg-white px-[1.5mm] py-[1mm] text-black shadow-sm ${printClass}`}>
            <div className="flex items-start justify-between gap-1">
                <p className="max-w-[24mm] truncate text-[6.5px] font-black leading-tight">{unit.product_name}</p>
                <p className="text-[5.5px] font-black uppercase leading-tight tracking-wide">NizPhone</p>
            </div>
            <div className="mt-[0.7mm]">
                <Code128Barcode value={value} className="h-[8mm] w-full" />
            </div>
            <p className="mt-[0.4mm] text-center font-mono text-[6.5px] font-black leading-none tracking-tight">{tagLabel(unit)}: {value}</p>
        </div>
    );
}

function BatchTagModal({ open, onClose, units, products, categories }: { open: boolean; onClose: () => void; units: Unit[]; products: Option[]; categories: Category[] }) {
    const [categoryId, setCategoryId] = useState("");
    const [productId, setProductId] = useState("");
    const [mode, setMode] = useState<"all" | "selected">("all");
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [query, setQuery] = useState("");
    const [showReport, setShowReport] = useState(false);

    const categoryProducts = useMemo(() => products.filter(product => !categoryId || String(product.category_id ?? "") === categoryId), [categoryId, products]);
    const scopedUnits = useMemo(() => units.filter(unit => {
        if (!tagValue(unit)) return false;
        if (categoryId && String(unit.product_category_id ?? "") !== categoryId) return false;
        if (productId && String(unit.product_id) !== productId) return false;
        const term = query.trim().toLowerCase();
        if (!term) return true;
        return `${unit.product_name} ${unit.identifier} ${unit.branch_name}`.toLowerCase().includes(term);
    }), [categoryId, productId, query, units]);
    const printableUnits = mode === "all" ? scopedUnits : scopedUnits.filter(unit => selected.has(unit.id));
    const canPrint = categoryId && printableUnits.length > 0;
    const reportPages = chunkArray(printableUnits, 48);

    const toggle = (id: number) => setSelected(prev => {
        const next = new Set(prev);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        return next;
    });
    const selectScoped = () => setSelected(new Set(scopedUnits.map(unit => unit.id)));
    const clearSelection = () => setSelected(new Set());

    if (!open) return null;

    return (
        <Modal title="A4 phone-back IMEI tag sheet" onClose={onClose} maxWidth="max-w-6xl">
            <div className="space-y-5 p-5">
                <style>{`
                    @media print {
                        @page { size: A4 portrait; margin: 10mm; }
                        body * { visibility: hidden !important; }
                        .a4-tag-report, .a4-tag-report * { visibility: visible !important; }
                        .a4-tag-report { position: absolute !important; inset: 0 auto auto 0 !important; width: 190mm !important; background: #fff !important; }
                        .a4-sheet { width: 190mm !important; min-height: 277mm !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: 0 !important; break-after: page; page-break-after: always; }
                        .a4-sheet:last-child { break-after: auto; page-break-after: auto; }
                        .a4-grid { display: grid !important; grid-template-columns: repeat(4, 37mm) !important; gap: 2.5mm 7mm !important; }
                        .imei-phone-tag { width: 37mm !important; height: 17mm !important; box-shadow: none !important; break-inside: avoid; }
                        .no-print { display: none !important; }
                    }
                `}</style>

                <div className="no-print overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/10 via-card to-card p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-[0.25em] text-primary">Premium tag report</p>
                            <h3 className="mt-1 text-2xl font-black">A4 PDF view for phone-back IMEI labels</h3>
                            <p className="mt-1 text-sm text-muted-foreground">Choose a category, generate an A4 sheet preview, then save or print from the report view.</p>
                        </div>
                        <div className="rounded-xl border border-border bg-background/70 px-4 py-3 text-right">
                            <p className="text-xs text-muted-foreground">Tags in sheet</p>
                            <p className="text-2xl font-black tabular-nums">{printableUnits.length}</p>
                        </div>
                    </div>
                </div>

                <div className="no-print grid gap-3 rounded-2xl border border-border bg-card p-4 lg:grid-cols-[1fr_1fr_1fr]">
                    <label className="text-xs font-medium">
                        Category first
                        <select className={`${input} mt-1`} value={categoryId} onChange={e => { setCategoryId(e.target.value); setProductId(""); clearSelection(); }}>
                            <option value="">Choose category…</option>
                            {categories.map(category => <option key={category.id} value={category.id}>{category.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-medium">
                        Product optional
                        <select className={`${input} mt-1`} value={productId} onChange={e => { setProductId(e.target.value); clearSelection(); }} disabled={!categoryId}>
                            <option value="">All products in category</option>
                            {categoryProducts.map(product => <option key={product.id} value={product.id}>{product.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-medium">
                        Find specific unit
                        <div className="relative mt-1">
                            <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                            <input className={`${input} pl-9`} value={query} onChange={e => setQuery(e.target.value)} placeholder="IMEI, serial, product, branch" />
                        </div>
                    </label>
                </div>

                <div className="no-print grid gap-3 md:grid-cols-2">
                    <button type="button" onClick={() => { setMode("all"); setShowReport(false); }} className={`rounded-2xl border p-4 text-left transition-all ${mode === "all" ? "border-primary bg-primary/10 shadow-sm" : "border-border hover:bg-muted/40"}`}>
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-primary/15 p-2 text-primary"><Layers3 className="h-5 w-5" /></div>
                            <div>
                                <p className="font-bold">All matching units</p>
                                <p className="text-xs text-muted-foreground">Print every unit under the chosen category/product.</p>
                            </div>
                        </div>
                    </button>
                    <button type="button" onClick={() => { setMode("selected"); setShowReport(false); }} className={`rounded-2xl border p-4 text-left transition-all ${mode === "selected" ? "border-primary bg-primary/10 shadow-sm" : "border-border hover:bg-muted/40"}`}>
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-primary/15 p-2 text-primary"><Check className="h-5 w-5" /></div>
                            <div>
                                <p className="font-bold">Select specific units only</p>
                                <p className="text-xs text-muted-foreground">Tick only the devices you want tags for.</p>
                            </div>
                        </div>
                    </button>
                </div>

                {mode === "selected" && (
                    <div className="no-print rounded-2xl border border-border">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-3">
                            <p className="text-sm font-bold">{selected.size} selected from {scopedUnits.length} matching</p>
                            <div className="flex gap-2">
                                <Button type="button" size="sm" variant="outline" onClick={selectScoped} disabled={!scopedUnits.length}>Select visible</Button>
                                <Button type="button" size="sm" variant="outline" onClick={clearSelection}>Clear</Button>
                            </div>
                        </div>
                        <div className="max-h-72 divide-y divide-border overflow-y-auto">
                            {scopedUnits.map(unit => (
                                <label key={unit.id} className="flex cursor-pointer items-center gap-3 p-3 hover:bg-muted/30">
                                    <input type="checkbox" checked={selected.has(unit.id)} onChange={() => toggle(unit.id)} className="h-4 w-4 accent-primary" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold">{unit.product_name}</p>
                                        <p className="font-mono text-xs text-primary">{tagLabel(unit)}: {tagValue(unit)}</p>
                                    </div>
                                    <span className="rounded-full bg-muted px-2 py-1 text-[10px] text-muted-foreground">{unit.branch_name}</span>
                                </label>
                            ))}
                            {categoryId && scopedUnits.length === 0 && <p className="p-8 text-center text-sm text-muted-foreground">No taggable units found for this filter.</p>}
                        </div>
                    </div>
                )}

                <div className="no-print rounded-2xl border border-border p-4">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p className="font-bold">Preview</p>
                            <p className="text-xs text-muted-foreground">Print size: 40mm × 20mm. Barcode value is IMEI when available.</p>
                        </div>
                        <Button type="button" onClick={() => setShowReport(true)} disabled={!canPrint}>
                            <Printer className="mr-2 h-4 w-4" />Generate A4 PDF view
                        </Button>
                    </div>
                    {!categoryId && <p className="rounded-xl bg-muted/40 p-6 text-center text-sm text-muted-foreground">Choose a category first to load printable tags.</p>}
                    {categoryId && printableUnits.length === 0 && <p className="rounded-xl bg-muted/40 p-6 text-center text-sm text-muted-foreground">No tags selected yet.</p>}
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {printableUnits.slice(0, 8).map(unit => <ImeiTag key={unit.id} unit={unit} />)}
                    </div>
                    {printableUnits.length > 8 && <p className="mt-3 text-center text-xs text-muted-foreground">Showing first 8 in preview. A4 report includes all {printableUnits.length} tags.</p>}
                </div>

                {showReport && (
                    <div className="rounded-2xl border border-border bg-muted/30 p-4">
                        <div className="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-bold">A4 PDF/report preview</p>
                                <p className="text-xs text-muted-foreground">{reportPages.length} page{reportPages.length === 1 ? "" : "s"} · {printableUnits.length} tag{printableUnits.length === 1 ? "" : "s"} · 48 tags per A4 page</p>
                            </div>
                            <Button type="button" variant="outline" onClick={() => window.print()}>
                                <Printer className="mr-2 h-4 w-4" />Print / Save as PDF
                            </Button>
                        </div>
                        <div className="a4-tag-report space-y-6">
                            {reportPages.map((pageUnits, pageIndex) => (
                                <div key={pageIndex} className="a4-sheet mx-auto min-h-[297mm] w-[210mm] rounded-sm border border-border bg-white p-[10mm] text-black shadow-xl">
                                    <div className="mb-[5mm] flex items-end justify-between border-b border-zinc-300 pb-[3mm]">
                                        <div>
                                            <p className="text-[13px] font-black uppercase tracking-[0.12em]">NizPhone IMEI Tag Sheet</p>
                                            <p className="mt-1 text-[10px] text-zinc-600">Small phone-back barcode labels · Page {pageIndex + 1} of {reportPages.length}</p>
                                        </div>
                                        <p className="text-[10px] font-bold text-zinc-500">{new Date().toLocaleDateString()}</p>
                                    </div>
                                    <div className="a4-grid grid grid-cols-4 gap-x-[7mm] gap-y-[2.5mm]">
                                        {pageUnits.map(unit => <ImeiTag key={unit.id} unit={unit} />)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}

export default function DeviceUnitsIndex({ units, tagUnits, stats, products, categories, branches, suppliers, filters, isSuperAdmin }: PageProps) {
    const [showAdd, setShowAdd] = useState(false);
    const [showBatchTags, setShowBatchTags] = useState(false);
    const [transferUnit, setTransferUnit] = useState<Unit | null>(null);
    const [tagUnit, setTagUnit] = useState<Unit | null>(null);
    const [search, setSearch] = useState(filters.search ?? "");
    const [status, setStatus] = useState(filters.status ?? "");
    const [branchFilter, setBranchFilter] = useState(filters.branch_id ?? "");
    const form = useForm({ product_id: "", branch_id: branches[0]?.id?.toString() ?? "", supplier_id: "", imei: "", imei_2: "", serial_number: "", cost: "", acquired_at: new Date().toISOString().slice(0, 10), warranty_months: "12", notes: "" });
    const transfer = useForm({ branch_id: "" });

    const applyFilters = (patch: Record<string, string>) => router.get('/device-units', { search, status, branch_id: branchFilter, ...patch }, { preserveState: true, replace: true });
    const submit = (e: FormEvent) => { e.preventDefault(); form.post('/device-units', { onSuccess: () => { setShowAdd(false); form.reset(); } }); };
    const submitTransfer = (e: FormEvent) => { e.preventDefault(); if (!transferUnit) return; transfer.post(`/device-units/${transferUnit.id}/transfer`, { onSuccess: () => { setTransferUnit(null); transfer.reset(); } }); };

    return <AdminLayout><Head title="Device Units" /><div className="mx-auto max-w-[1500px] space-y-5 p-1">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 className="text-xl font-bold">IMEI / Serial Inventory</h1>
                <p className="text-xs text-muted-foreground">Track every physical gadget from receiving to sale and warranty.</p>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button variant="outline" onClick={() => setShowBatchTags(true)}><Printer className="mr-2 h-4 w-4" />Print Tags</Button>
                <Button onClick={() => setShowAdd(true)}><Plus className="mr-2 h-4 w-4" />Register unit</Button>
            </div>
        </div>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
            {[{label:'Total units',value:stats.total,Icon:Boxes}, {label:'Available',value:stats.available,Icon:Smartphone}, {label:'Sold',value:stats.sold,Icon:Truck}, {label:'In service',value:stats.in_service,Icon:ShieldCheck}, {label:'Active warranty',value:stats.warranty_active,Icon:ShieldCheck}].map(({ label, value, Icon }) => <div key={label} className="rounded-xl border border-border bg-card p-4"><div className="flex items-center justify-between"><p className="text-xs text-muted-foreground">{label}</p><Icon className="h-4 w-4 text-primary" /></div><p className="mt-2 text-2xl font-bold">{Number(value).toLocaleString()}</p></div>)}
        </div>
        <div className="grid gap-2 rounded-xl border border-border bg-card p-2 md:grid-cols-[minmax(220px,1fr)_160px_180px_auto]">
            <div className="relative"><Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" /><input className={`${compactInput} pl-9`} value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && applyFilters({ search })} placeholder="IMEI, serial, or product" /></div>
            <select className={compactInput} value={status} onChange={e => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}><option value="">All statuses</option>{['available','sold','in_service','damaged','returned'].map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}</select>
            {isSuperAdmin ? <select className={compactInput} value={branchFilter} onChange={e => { setBranchFilter(e.target.value); applyFilters({ branch_id: e.target.value }); }}><option value="">All branches</option>{branches.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}</select> : <div className="hidden md:block" />}
            <Button className="h-9" variant="outline" onClick={() => applyFilters({ search })}>Search</Button>
        </div>
        <div className="overflow-hidden rounded-xl border border-border bg-card"><div className="overflow-x-auto"><table className="w-full text-sm"><thead className="border-b border-border bg-muted/30 text-left text-[10px] uppercase tracking-wider text-muted-foreground"><tr>{['Device / identifier','Branch','Status','Sale / customer','Warranty','Supplier','Actions'].map(h => <th key={h} className="whitespace-nowrap px-4 py-3">{h}</th>)}</tr></thead><tbody className="divide-y divide-border">
            {units.data.map(unit => <tr key={unit.id} className="hover:bg-muted/20"><td className="px-4 py-3"><p className="font-semibold">{unit.product_name}</p><p className="font-mono text-xs text-primary">{unit.identifier}</p>{unit.imei_2 && <p className="font-mono text-[10px] text-muted-foreground">IMEI 2: {unit.imei_2}</p>}</td><td className="px-4 py-3 text-muted-foreground">{unit.branch_name}</td><td className="px-4 py-3"><span className={`rounded-full px-2 py-1 text-xs ${statusStyle[unit.status] ?? 'bg-muted'}`}>{unit.status.replace('_',' ')}</span></td><td className="px-4 py-3"><p>{unit.receipt_number ?? '—'}</p><p className="text-xs text-muted-foreground">{unit.customer_name}</p></td><td className="px-4 py-3"><p>{unit.warranty_expires_at ?? 'Not started'}</p>{unit.warranty_status !== 'not_started' && <p className={`text-xs ${unit.warranty_status === 'active' ? 'text-emerald-500' : 'text-red-500'}`}>{unit.warranty_status}</p>}</td><td className="px-4 py-3 text-muted-foreground">{unit.supplier_name ?? '—'}</td><td className="px-4 py-3"><div className="flex flex-wrap gap-1"><Button size="sm" variant="outline" disabled={!tagValue(unit)} title={tagValue(unit) ? 'Generate scan tag' : 'No IMEI or serial available for tag'} onClick={() => tagValue(unit) && setTagUnit(unit)}><Barcode className="mr-1 h-3.5 w-3.5" />Tag</Button>{unit.status === 'available' && <><Button size="sm" variant="outline" onClick={() => { setTransferUnit(unit); transfer.setData('branch_id', ''); }}>Transfer</Button><button className="rounded p-2 text-muted-foreground hover:text-destructive" onClick={() => confirm(`Remove ${unit.identifier}?`) && router.delete(`/device-units/${unit.id}`)}><Trash2 className="h-4 w-4" /></button></>}</div></td></tr>)}
            {units.data.length === 0 && <tr><td colSpan={7} className="p-12 text-center text-muted-foreground">No serialized units found.</td></tr>}
        </tbody></table></div><div className="flex flex-wrap gap-1 border-t border-border p-3">{units.links.map((link, i) => <button key={i} disabled={!link.url} onClick={() => link.url && router.get(link.url, {}, { preserveState: true })} className={`rounded px-3 py-1 text-xs ${link.active ? 'bg-primary text-primary-foreground' : 'border border-border'} disabled:opacity-40`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
    </div>
    <BatchTagModal open={showBatchTags} onClose={() => setShowBatchTags(false)} units={tagUnits} products={products} categories={categories} />
    {showAdd && <Modal title="Register serialized unit" onClose={() => setShowAdd(false)}><form onSubmit={submit} className="grid grid-cols-2 gap-4 p-4">
        <div className="col-span-2 text-xs">
            Product
            <SearchableProductSelect
                products={products}
                value={form.data.product_id}
                onChange={(value, product) => {
                    form.setData('product_id', value);
                    if (product?.warranty_months != null) form.setData('warranty_months', String(product.warranty_months));
                }}
                placeholder="Select product"
                className="mt-1"
            />
            <span className="text-destructive">{form.errors.product_id}</span>
        </div>
        <label className="text-xs">Branch<select className={`${input} mt-1`} value={form.data.branch_id} onChange={e => form.setData('branch_id', e.target.value)} required>{branches.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}</select></label><label className="text-xs">Supplier<select className={`${input} mt-1`} value={form.data.supplier_id} onChange={e => form.setData('supplier_id', e.target.value)}><option value="">Not specified</option>{suppliers.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}</select></label>
        <label className="text-xs">IMEI 1<input className={`${input} mt-1 font-mono`} inputMode="numeric" maxLength={15} value={form.data.imei} onChange={e => form.setData('imei', e.target.value.replace(/\D/g,''))} placeholder="15 digits" /><span className="text-destructive">{form.errors.imei}</span></label><label className="text-xs">IMEI 2<input className={`${input} mt-1 font-mono`} inputMode="numeric" maxLength={15} value={form.data.imei_2} onChange={e => form.setData('imei_2', e.target.value.replace(/\D/g,''))} placeholder="Optional" /><span className="text-destructive">{form.errors.imei_2}</span></label>
        <label className="col-span-2 text-xs">Serial number<input className={`${input} mt-1 font-mono`} value={form.data.serial_number} onChange={e => form.setData('serial_number', e.target.value)} placeholder="Required when no IMEI" /><span className="text-destructive">{form.errors.serial_number}</span></label>
        <label className="text-xs">Unit cost<input type="number" min="0" step="0.01" className={`${input} mt-1`} value={form.data.cost} onChange={e => form.setData('cost', e.target.value)} /></label><label className="text-xs">Received date<input type="date" className={`${input} mt-1`} value={form.data.acquired_at} onChange={e => form.setData('acquired_at', e.target.value)} /></label><label className="text-xs">Warranty months<input type="number" min="0" max="120" className={`${input} mt-1`} value={form.data.warranty_months} onChange={e => form.setData('warranty_months', e.target.value)} required /></label><label className="text-xs">Notes<input className={`${input} mt-1`} value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} /></label>
        <div className="col-span-2 flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setShowAdd(false)}>Cancel</Button><Button disabled={form.processing}>Register and add stock</Button></div>
    </form></Modal>}
    {tagUnit && <Modal title="A4 IMEI tag PDF preview" onClose={() => setTagUnit(null)} maxWidth="max-w-5xl">
        <div className="space-y-4 p-4">
            <style>{`
                @media print {
                    @page { size: A4 portrait; margin: 10mm; }
                    body * { visibility: hidden !important; }
                    .single-a4-report, .single-a4-report * { visibility: visible !important; }
                    .single-a4-report { position: absolute !important; inset: 0 auto auto 0 !important; width: 190mm !important; background: #fff !important; }
                    .single-a4-sheet { width: 190mm !important; min-height: 277mm !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: 0 !important; }
                    .imei-phone-tag { width: 37mm !important; height: 17mm !important; box-shadow: none !important; break-inside: avoid; }
                    .no-print { display: none !important; }
                }
            `}</style>
            <div className="no-print rounded-xl border border-primary/20 bg-primary/5 p-3 text-sm">
                <p className="font-semibold text-foreground">PDF/report preview first</p>
                <p className="mt-1 text-xs text-muted-foreground">
                    This opens an A4 sheet preview for this one unit. Use the button below only when you are ready to print or save it as PDF.
                </p>
                <p className="mt-2 text-xs text-muted-foreground">
                    Scanner value: <span className="font-mono font-semibold text-foreground">{tagValue(tagUnit)}</span>
                </p>
            </div>
            <div className="single-a4-report overflow-x-auto rounded-2xl bg-muted/30 p-4">
                <div className="single-a4-sheet mx-auto min-h-[297mm] w-[210mm] rounded-sm border border-border bg-white p-[10mm] text-black shadow-xl">
                    <div className="mb-[6mm] flex items-start justify-between border-b border-zinc-200 pb-[4mm]">
                        <div>
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] text-zinc-500">NizPhone Gadgets</p>
                            <h3 className="text-lg font-black">Single IMEI Tag Sheet</h3>
                            <p className="text-xs text-zinc-500">A4 PDF-ready preview for phone-back scan label</p>
                        </div>
                        <div className="text-right text-[10px] text-zinc-500">
                            <p>{new Date().toLocaleDateString()}</p>
                            <p>1 tag</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-[7mm]">
                        <ImeiTag unit={tagUnit} />
                    </div>
                </div>
            </div>
            <div className="no-print flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => setTagUnit(null)}>Close</Button>
                <Button type="button" onClick={() => window.print()}><Printer className="mr-2 h-4 w-4" />Print / Save as PDF</Button>
            </div>
        </div>
    </Modal>}
    {transferUnit && <Modal title={`Transfer ${transferUnit.identifier}`} onClose={() => setTransferUnit(null)}><form onSubmit={submitTransfer} className="space-y-4 p-4"><p className="text-sm text-muted-foreground">Move this exact unit and one stock quantity from {transferUnit.branch_name}.</p><select className={input} value={transfer.data.branch_id} onChange={e => transfer.setData('branch_id', e.target.value)} required><option value="">Destination branch</option>{branches.filter(b => b.id !== transferUnit.branch_id).map(b => <option key={b.id} value={b.id}>{b.name}</option>)}</select><div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setTransferUnit(null)}>Cancel</Button><Button disabled={transfer.processing}>Transfer unit</Button></div></form></Modal>}
    </AdminLayout>;
}
