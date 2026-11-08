<x-layouts.admin title="Add user">
    <x-admin.page-title title="Add user" subtitle="Create any account and choose its role." />

    @include('Admin.users._form', ['action' => route('admin.users.store')])
</x-layouts.admin>
