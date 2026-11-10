<script>
(() => {
    const form = document.getElementById('sbs-record-form');
    const field = name => form.elements.namedItem(name);
    const appeal = form.querySelector('[data-appeal-section]');
    const expiry = form.querySelector('[data-expiry-field]');
    const refresh = () => {
        const refused = field('outcome').value === 'Refused';
        const approved = field('outcome').value === 'Approved' || (refused && field('final_outcome').value === 'Approved');
        appeal.hidden = !refused;
        appeal.disabled = !refused;
        field('outcome_date').required = ['Approved', 'Refused', 'Withdrawn'].includes(field('outcome').value);
        field('final_outcome_date').required = refused && !!field('final_outcome').value;
        field('appeal_deadline').disabled = !refused || field('appeal_status').value === 'Not reviewable';
        if (refused && field('appeal_status').value === 'Not reviewable') field('appeal_deadline').value = '';
        expiry.hidden = !approved;
        field('expiry_date').disabled = !approved;
        field('expiry_date').required = approved;
        form.querySelector(refused ? '[data-final-expiry-slot]' : '[data-original-expiry-slot]').append(expiry);
        field('trn').required = field('status').value === 'Active';
    };
    ['outcome', 'final_outcome', 'appeal_status', 'status'].forEach(name => field(name).addEventListener('change', refresh));
    field('application_stage').addEventListener('change', () => {
        if (field('application_stage').value === 'Lodged' && @json($application->application_stage) !== 'Lodged') {
            field('status').value = 'Pending Decision';
            field('outcome').value = 'Awaiting decision';
            field('outcome_date').value = '';
        }
        refresh();
    });
    refresh();
})();
</script>
