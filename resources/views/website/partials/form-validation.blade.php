<style>
    form[novalidate] [aria-invalid="true"] {
        border-color: #b91c1c !important;
    }

    form[novalidate] .psc-field-error {
        color: #b91c1c;
    }
</style>

@if ($errors->any())
    <script>
        document.querySelector('form[novalidate] [aria-invalid="true"]')?.focus();
    </script>
@endif
