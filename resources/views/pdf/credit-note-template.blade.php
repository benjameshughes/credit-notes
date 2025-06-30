<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Note{{ !empty($data['Reference']) ? ' ' . $data['Reference'] : '' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #111827;
            line-height: 1.5;
            background: #ffffff;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
        }
        
        /* Header */
        .header {
            background: #2563eb;
            color: #ffffff;
            padding: 1.5rem;
        }
        
        .header-content {
            width: 100%;
        }
        
        .company-info {
            float: left;
            width: 60%;
        }
        
        .document-title {
            float: right;
            width: 35%;
            text-align: right;
        }
        
        .company-info h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .company-address {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .document-title {
            text-align: right;
        }
        
        .document-title h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .document-number {
            font-size: 1rem;
        }
        
        /* Content */
        .content {
            padding: 1.5rem;
        }
        
        .section {
            margin-bottom: 1.5rem;
        }
        
        /* Details Card */
        .details-card {
            background: #f9fafb;
            border-left: 3px solid #2563eb;
            padding: 1rem;
            border-radius: 0 0.25rem 0.25rem 0;
        }
        
        .details-title {
            font-size: 1rem;
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .details-grid {
            width: 100%;
        }
        
        .detail-row {
            width: 48%;
            float: left;
            margin-right: 2%;
            margin-bottom: 0.75rem;
        }
        
        .detail-label {
            color: #4b5563;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-block;
            width: 80px;
        }
        
        .detail-value {
            font-weight: 500;
            font-size: 0.875rem;
            display: inline;
        }
        
        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.25rem;
            margin-left: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .badge-outstanding {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-settled {
            background: #dcfce7;
            color: #166534;
        }
        
        /* Table */
        .table-container {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead {
            background: #2563eb;
            color: #ffffff;
        }
        
        .table th {
            padding: 0.75rem 1rem;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .table th:first-child {
            text-align: left;
        }
        
        .table th:last-child {
            text-align: right;
        }
        
        .table tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table td {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }
        
        .table td:last-child {
            text-align: right;
            font-weight: 500;
        }
        
        .table .total-row {
            background: #2563eb;
            color: #ffffff;
            font-weight: 700;
        }
        
        .table .total-row td:last-child {
            font-size: 1rem;
        }
        
        .table .outstanding-row {
            background: #dc2626;
            color: #ffffff;
            font-weight: 600;
        }
        
        .table .outstanding-row td:last-child {
            font-size: 1rem;
        }
        
        /* Alert */
        .alert {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 0.5rem;
        }
        
        .alert-icon {
            font-size: 1.25rem;
            color: #f59e0b;
            float: left;
            margin-right: 0.75rem;
        }
        
        .alert-content {
            color: #92400e;
            font-weight: 500;
        }
        
        .alert-content .font-bold {
            font-weight: 700;
        }
        
        /* Footer */
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .footer p {
            margin-bottom: 0.25rem;
        }
        
        /* Clearfix */
        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content clearfix">
                <div class="company-info">
                    <h1>The Blinds Outlet</h1>
                    <div class="company-address">
                        <div>2 Fleming Road</div>
                        <div>Corby, Northampton</div>
                        <div>NN17 4SW</div>
                        <div>VAT Number: GB311015965</div>
                    </div>
                </div>
                <div class="document-title">
                    <h2>CREDIT NOTE</h2>
                    @if(!empty($data['Reference']))
                    <div class="document-number">{{ $data['Reference'] }}</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Credit Note Details -->
            <div class="section">
                <div class="details-card">
                    <h3 class="details-title">Credit Note Details</h3>
                    <div class="details-grid clearfix">
                        @if(!empty($data['Reference']))
                        <div class="detail-row">
                            <span class="detail-label">Reference:</span>
                            <span class="detail-value">{{ $data['Reference'] }}</span>
                        </div>
                        @endif
                        @if(!empty($data['Date']))
                        <div class="detail-row">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value">{{ $data['Date'] }}</span>
                        </div>
                        @endif
                        @if(!empty($data['Number']))
                        <div class="detail-row">
                            <span class="detail-label">Number:</span>
                            <span class="detail-value">{{ $data['Number'] }}</span>
                        </div>
                        @endif
                        @if(!empty($data['Type']))
                        <div class="detail-row">
                            <span class="detail-label">Type:</span>
                            <span class="detail-value">{{ $data['Type'] }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Amounts Table -->
            <div class="section">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount (GBP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(!empty($data['Net GBP']))
                            <tr>
                                <td>Net Amount</td>
                                <td>£{{ $data['Net GBP'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($data['VAT GBP']))
                            <tr>
                                <td>VAT Amount</td>
                                <td>£{{ $data['VAT GBP'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($data['Discount GBP']))
                            <tr>
                                <td>Discount Applied</td>
                                <td>-£{{ $data['Discount GBP'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($data['Total GBP']))
                            <tr class="total-row">
                                <td>Total Credit Amount</td>
                                <td>£{{ $data['Total GBP'] }}</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Footer -->
            <div class="footer">
                <p><strong>The Blinds Outlet LTD</strong> | VAT Number: GB311015965</p>
            </div>
        </div>
    </div>
</body>
</html>