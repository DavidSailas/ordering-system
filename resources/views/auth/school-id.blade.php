<x-layouts.guest title="Confirm your school ID" variant="customer" wide>
    <h1>Confirm your school ID</h1>
    <p class="auth-sub">Ordering is only for our students and employees. Enter the ID number and last name from your school record, one time only.</p>

    <form method="POST" action="{{ route('school-id.store') }}" class="auth-form" data-auth-form>
        @csrf
        <div class="auth-grid-2">
            <x-auth-field name="school_id" label="Student / employee ID" placeholder="2024-0001" autocomplete="off" required autofocus />
            <x-auth-field name="last_name" label="Last name" placeholder="Dela Cruz" autocomplete="family-name" required />
        </div>

        <button class="auth-submit">Confirm my ID</button>
    </form>

    <p class="auth-alt">Not you? <a href="#" onclick="event.preventDefault();document.getElementById('signout-form').submit();">Sign out</a></p>
    <form id="signout-form" method="POST" action="{{ route('logout') }}" hidden>@csrf</form>
</x-layouts.guest>
