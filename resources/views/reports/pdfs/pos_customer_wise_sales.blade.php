<!DOCTYPE html>
<html lang="en">
<head>
    <title>Product wise sales report</title>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <style type="text/css">
        @import url('https://fonts.maateen.me/mukti/font.css');


        .page-break {
            page-break-after: always;
        }

        .break-before {
            page-break-before: auto;
        }
        body {
            font-family: 'Mukti',  'Roboto',sans-serif;
            color: #4a4a4a;
            font-style: normal;
            font-weight: normal;
        }

        .heading {
            text-align: center;
            margin-top: 20px;
        }

        .heading h2 {
            font-size: 1.5rem;
        }

        .heading .sub-heading {
            font-size: 1rem;
            font-family: 'Mukti','Roboto',sans-serif;
        }

        .heading .sub-text {
            font-size: .9rem;
            font-family: 'Mukti','Roboto',sans-serif;
        }

        .table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            font-family: 'Mukti','Roboto',sans-serif;
        }

        th {
            padding: 10px;
        }

        .table-head {
            background-color: #ededed;
            font-weight: normal;
        }

        .table-head th {
            font-family: 'Mukti','Roboto',sans-serif;
            font-weight: normal;
        }
        @page {
            margin: 40px;
            padding: 2cm;
        }

        @media print {

            .table {
                page-break-inside: auto !important;
            }

            .table tbody tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }

        }
    </style>
</head>
<body align="center">
<table style="max-width: 800px;margin: auto;min-width: 600px;>
    <tbody>
    <tr>
        <td>
            <div class="heading">
                <h2>{{ ucfirst($partner->name) }}</h2>
                <h4 class="sub-heading"> কাস্টমার অনুযায়ী বিক্রয় রিপোর্ট </h4>
                <span class="sub-text">{{convertNumbersToBangla($from->day,false).' '.banglaMonth($from->month).' '.convertNumbersToBangla($from->year,false).' থেকে '.convertNumbersToBangla($to->day,false).' '.banglaMonth($to->month).' '.convertNumbersToBangla($to->year,false)}}</span>
            </div>
        </td>
    </tr>
    </tbody>
</table>
<div>
    <table class="table table-bordered">
        <thead>
        <tr class="table-head">
            <th> গ্রাহকের  </th>
            <th> অর্ডার সংখ্যা</th>
            <th> বিক্রয়মূল্য</th>
        </tr>
        </thead>
        <tbody>
        <?php $totalOrder = $totalPrice = 0;?>
        @foreach($data as $item)
            <tr>
                <?php $totalPrice += (float)$item['sales_amount'];
                $totalOrder += (float)$item['order_count']; ?>
                <td>{{ $item['customer_name'] }}</td>
                <td>{{ convertNumbersToBangla((float)$item['order_count']) }}</td>
                <td>{{ convertNumbersToBangla((float)$item['sales_amount']) }}</td>
            </tr>
        @endforeach

        <tr style="page-break-after: always" class="table-head">
            <td><span class="font-weight-bold"> মোট</span></td>
            <td><span class="font-weight-bold">{{ convertNumbersToBangla((float)$totalOrder) }}</span></td>
            <td><span class="font-weight-bold">{{ convertNumbersToBangla((float)$totalPrice) }}</span></td>
        </tr>
        </tbody>
    </table>
</div>
</body>
</html>
