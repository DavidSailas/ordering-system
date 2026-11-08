<x-layouts.admin title="Add staff">
    <x-admin.page-title title="Add staff" subtitle="Create a login for a cashier or a runner." />

    @include('Admin.staff._form', ['action' => route('admin.staff.store')])
</x-layouts.admin>
