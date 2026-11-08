<x-layouts.admin title="Edit user">
    <x-admin.page-title title="Edit user" :subtitle="$member->name" />

    @include('Admin.users._form', ['action' => route('admin.users.update', $member)])
</x-layouts.admin>
