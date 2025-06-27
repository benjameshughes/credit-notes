{{-- resources/views/pdf/customer-template.blade.php --}}
        <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Information</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #333; }
        .value { margin-left: 10px; }
    </style>
</head>
<body>
<div class="header">
    <h1>Customer Information</h1>
</div>

<div class="content">
    @foreach($data as $key => $value)
        <div class="field">
            <span class="label">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
            <span class="value">{{ $value }}</span>
        </div>
    @endforeach
</div>

<div style="margin-top: 40px; text-align: center; color: #666; font-size: 12px;">
    Generated on {{ date('Y-m-d H:i:s') }}
</div>
</body>
</html>