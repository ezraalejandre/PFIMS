@if($errors->any())
<div role="alert" class="pfims-validation-errors" style="position:fixed;top:90px;right:20px;z-index:99999;max-width:min(420px,90vw);padding:16px 20px;border:1px solid #c84242;border-radius:10px;background:#fff1f1;color:#8f2020;box-shadow:0 8px 24px #0002;">
    <strong>Invalid input</strong><p>{{ $errors->first() }}</p>
    <button type="button" aria-label="Dismiss invalid input alert" onclick="this.parentElement.remove()">Close</button>
</div>
@endif
