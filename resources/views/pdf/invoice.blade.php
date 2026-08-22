<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-8 antialiased">
    @include('invoices._document', ['invoice' => $invoice])
</body>
</html>
