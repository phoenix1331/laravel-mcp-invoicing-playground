<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>{!! file_get_contents(public_path(parse_url(Vite::asset('resources/css/app.css'), PHP_URL_PATH))) !!}</style>
</head>
<body class="bg-white p-8 antialiased">
    @include('invoices._document', ['invoice' => $invoice])
</body>
</html>
