@if (session()->has('success'))
    <div role="alert" class="mt-3 relative flex w-full p-3 text-sm text-teal-900 bg-teal-100 rounded-md border-t-4 border-teal-500" id="alert">
        {{session('success')}}
        <button class="flex items-center justify-center transition-all w-8 h-8 rounded-md text-teal-900 hover:bg-teal-200 active:bg-teal-200 absolute top-1.5 right-1.5" type="button" onclick="closeAlert()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-5 w-5" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif

<script>
    function closeAlert() {
      const alertElement = document.getElementById('alert');
      alertElement.style.display = 'none'; // Hides the alert
    }
</script>
