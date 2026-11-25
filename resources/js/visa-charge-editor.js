import Alpine from 'alpinejs';

function visaChargeEditor(initial, amounts, today, endpoint, token) {
    let key = 0;
    const makeRow = (row = {}) => ({
        name: '', description: '', effective: '',
        ...Object.fromEntries(amounts.map(field => [field, ''])),
        ...row, key: ++key,
    });
    const original = () => initial.map(group => ({
        ...group,
        rows: group.rows.map(row => makeRow(Object.fromEntries(
            Object.entries(row).map(([field, value]) => [field, value ?? ''])
        ))),
    }));
    const payloadRows = rows => rows.map(row => Object.fromEntries(
        ['name', 'description', 'effective', ...amounts].map(field => [field, row[field] || null])
    ));
    const currency = new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' });
    const sorted = rows => [...rows].sort((a, b) =>
        a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }) ||
        b.effective.localeCompare(a.effective)
    );
    const current = (rows, date) => {
        const seen = new Set();
        return sorted(rows).filter(row => {
            const name = row.name.trim().toLowerCase();
            if (row.effective > date || seen.has(name)) return false;
            seen.add(name);
            return true;
        });
    };

    return {
        groups: original(), editing: false, currentOnly: true, saving: false, error: '',
        bulkOpen: false, bulkDate: '', bulkEffective: '',
        variations: group => group.rows.some(row => row.variation || row.name),
        needsDate: row => row.locked || amounts.some(field => Number(row[field]) > 0),
        dateLabel: date => date ? date.split('-').reverse().join('/') : '—',
        money: amount => Number(amount) > 0 ? currency.format(amount) : '—',
        visibleRows(group) {
            if (this.editing) return group.rows;
            return this.currentOnly ? current(group.rows, today) : sorted(group.rows);
        },
        addVariation(group) {
            if (!this.variations(group)) {
                group.rows.forEach(row => { row.variation = true; });
            }
            group.rows.push(makeRow({ variation: true }));
        },
        change(group, row, effective = '', bulk = false) {
            group.rows.splice(group.rows.indexOf(row), 0, makeRow({ ...row, effective, locked: true, bulk }));
        },
        remove(group, row) {
            if (confirm('Delete this variation price? Prices already saved to applications will be kept.')) {
                group.rows.splice(group.rows.indexOf(row), 1);
            }
        },
        bulkChange() {
            const date = this.bulkDate;
            for (const group of this.groups) {
                for (const row of current(group.rows.filter(row => row.effective && row.effective < date), date)) {
                    if (!group.rows.some(item => item.effective === date && item.name.trim().toLowerCase() === row.name.trim().toLowerCase())) {
                        this.change(group, row, date, true);
                    }
                }
            }
            this.bulkEffective = date;
            this.bulkOpen = false;
        },
        cancel() {
            this.groups = original();
            this.editing = this.bulkOpen = false;
            this.bulkDate = this.bulkEffective = this.error = '';
        },
        validationError() {
            if (!this.editing) return '';
            for (const group of this.groups) {
                const dates = new Set();
                for (const row of group.rows) {
                    if (this.variations(group) && !row.name.trim()) return 'Enter a name for each variation.';
                    if (this.needsDate(row) && !row.effective) return 'Enter an effective date.';
                    const key = row.name.trim().toLowerCase() + '|' + row.effective;
                    if (dates.has(key)) return 'Another row for this charge has this date';
                    dates.add(key);
                }
            }
            return '';
        },
        async save() {
            this.saving = true;
            this.error = '';
            try {
                const groups = this.groups.flatMap((group, index) => {
                    const rows = payloadRows(group.rows);
                    return JSON.stringify(rows) === JSON.stringify(payloadRows(initial[index].rows))
                        ? []
                        : [{ id: group.id, revision: group.revision, rows }];
                });
                if (!groups.length) {
                    this.cancel();
                    this.saving = false;
                    return;
                }
                const response = await fetch(endpoint, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ groups }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Unable to save charges.');
                location.reload();
            } catch (error) {
                this.error = error.message;
                this.saving = false;
            }
        },
    };
}

Alpine.data('visaChargeEditor', visaChargeEditor);
Alpine.start();
