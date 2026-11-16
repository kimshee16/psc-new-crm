<style>
    .rfi-table { width: 100%; min-width: 1350px; border-collapse: collapse; text-align: left; font-size: 14px; }
    .rfi-table th, .rfi-table td { min-width: 120px; padding: 14px 12px; vertical-align: top; }
    .rfi-table th { background: #f8fafc; font-size: 12px; color: #475569; }
    .rfi-table td { border-top: 1px solid #e2e8f0; }
    .rfi-table tbody tr:nth-child(even) { background: #f8fafc; }
    .rfi-table th:nth-child(2) { min-width: 190px; }
    .rfi-table [data-rfi-sort] { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
    .rfi-filter { display: block; width: 100%; margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; background: white; font-weight: 400; }
    .rfi-date-filter { width: 150px; }
    .rfi-dialog { width: min(600px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); margin: auto; border: 0; border-radius: 12px; padding: 24px; box-shadow: 0 24px 50px #0004; }
    .rfi-dialog::backdrop { background: #20282e99; }
    .rfi-editor label { display: block; min-width: 0; font-size: 14px; font-weight: 600; }
    .rfi-editor [hidden] { display: none !important; }
    .rfi-editor input:not([type="hidden"]), .rfi-editor select, .rfi-editor textarea { display: block; width: 100%; margin-top: 6px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; font-weight: 400; background: white; }
    .rfi-editor [aria-invalid="true"] { border-color: #b91c1c !important; }
    .rfi-editor :focus-visible, .rfi-table :focus-visible { outline: 2px solid #1f7890; outline-offset: 3px; }
</style>
