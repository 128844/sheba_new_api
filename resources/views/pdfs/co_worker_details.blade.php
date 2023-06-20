<!DOCTYPE html>

<html lang="en">

<head>
    <!-- start: Meta -->
    <title>Co Worker Details</title>
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="keyword" content="">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        @media print {
            table { page-break-after: auto; page-break-inside: auto; }
            tr    { page-break-inside:avoid; page-break-after:auto }
        }
        ​@font-face {
            font-family: 'Poppins', sans-serif;
        }
        .text-center{
            text-align: center;
        }
        .text-right{
            text-align: right;
        }
        .text-left{
            text-align: left;
        }
        body {
            counter-reset: page;
        }
        /*
         !*new styles*!*/
        table, th {
            border: solid 1px #d2d8e6;
            border-collapse: collapse;
        }

        .tableHeadRegular{
            opacity: 0.8;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: bold;
            padding: 9px 20px;
            text-align: left;
            background-color: #fff8f8fb;
        }

        .tableRowValue {
            font-size: 10px;
            font-weight: 400;
            font-family: 'Lato', sans-serif;
            padding: 5px 10px;
            border-bottom: solid 1px #d2d8e6;
        }

        .employeeBasicInfo {
            vertical-align: top;
            font-family: 'Lato', sans-serif;
            font-size: 10px;
            font-weight: 400;
            color: #000000;
            padding-bottom: 10px
        }

        @page {
            margin-top: 20px;
        }
        .header{
            top: 0;
            left: 0;
            width: 100%;
            position: fixed;
            padding: 0;
            margin: 100px 0 0 0;
            background-color: #fff;
            border: none;
        }

        .company-name {
            margin: 0;
            padding-top: 27px;
            font-family: 'Poppins', sans-serif;
            opacity: 0.8;
            font-size: 18px;
            font-weight: 500;
            color: #000000;
        }

        .pdf-title {
            margin: 0;
            opacity: 0.8;
            padding: 0 0 25px 0;
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 600;
            color: #000000;
        }

        ​/*new styles end*/
    </style>


</head>

<body style="margin-top: 20px; font-family: 'Poppins', sans-serif;">

{{--<body style="margin: 50px 30px; font-family: 'Poppins', sans-serif; ">--}}

<table class="header">
    <tr>
        @if($employee['pdf_info']['company_logo'])
            <td class="text-left"><img src="{{ $employee['pdf_info']['company_logo'] }}" height="65"/></td>
        @endif
        @if($employee['pdf_info']['company_name'])
            <td class="text-right"><p class="company-name">{{$employee['pdf_info']['company_name']}}</p>
            </td>
        @endif
    </tr>
    <tr>
        <td><hr style=" color: #d1d7e6; width: 720px"></td>
    </tr>
</table>

<table style="border: none">
    <tr>
        <td>
            <p class="pdf-title">Employee Detail</p>
        </td>
    </tr>
</table>

<table style="width: 100%; border: none; margin-left: 6px">
    <tr>
        <td style="width: 80%; border : none; vertical-align: top;">
            <table style="width: 100%; border : none">
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Employee Name</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['basic_info']['profile']['name'] }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Email</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['basic_info']['profile']['email'] }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Department</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['basic_info']['department'] }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Designation</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['basic_info']['designation'] }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Manager</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['basic_info']['manager_detail']['name'] }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Date of joining</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['pdf_info']['joining_date'] }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Deactivation Date</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['pdf_info']['deactivated_date'] }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Employee Type</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['official_info']['employee_type'] ? ucfirst($employee['official_info']['employee_type']['title']) : 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Employee ID</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['official_info']['employee_id'] ?  : 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="employeeBasicInfo" style="width: 97px">Grade</td>
                    <td class="employeeBasicInfo" style="width: 20px">:</td>
                    <td class="employeeBasicInfo" style="width: 400px">{{ $employee['official_info']['grade'] ?  : 'N/A' }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 20%">
            <table style="border: none;margin-top: -150px;margin-left: 20px">
                <tr>
                    @if($employee['basic_info']['profile']['profile_picture'])
                    <td><img src="{{ $employee['basic_info']['profile']['profile_picture'] }}" style="height: 90px;width: 90px;border-radius: 50%;"/></td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
</table>


<table class="tableHead" style="width: 100%;margin-top: 20px">

    <thead>
    <tr class="tableHeadRegular" style="background: #f8f8fb; width: 100%">
        <td style="width:15%;font-size: 12px; opacity: 0.8; font-weight: bold; font-family: 'Poppins', sans-serif; padding: 10px;border-bottom: solid 1px #d2d8e6;">
            Personal
        </td>
        <td style="width:3%;border-bottom: solid 1px #d2d8e6;">

        </td>
        <td style="width:82%;border-bottom: solid 1px #d2d8e6;">

        </td>
    </tr>
    </thead>

    <tbody>
    <tr>
        <td class="tableRowValue" style="width: 15%">Gender</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['personal_info']['gender'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Phone</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['personal_info']['mobile'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Date of Birth</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['pdf_info']['date_of_birth'] }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Address</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['personal_info']['address'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Nationality</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['personal_info']['nationality'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">NID</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['personal_info']['nid_no'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Passport</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['personal_info']['passport_no'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Blood Group</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['personal_info']['blood_group'] ?  : 'N/A' }}</td>
    </tr>
    </tbody>

</table>


<table class="tableHead" style="width: 100%;margin-top: 24px">

    <thead>
    <tr class="tableHeadRegular" style="background: #f8f8fb; width: 100%">
        <td style="width:15%;font-size: 12px; opacity: 0.8; font-weight: bold; font-family: 'Poppins', sans-serif; padding: 10px;border-bottom: solid 1px #d2d8e6;">
            Financial
        </td>
        <td style="width:3%;border-bottom: solid 1px #d2d8e6;">

        </td>
        <td style="width:82%;border-bottom: solid 1px #d2d8e6;">

        </td>
    </tr>
    </thead>

    <tbody>
    <tr>
        <td class="tableRowValue" style="width: 15%">TIN</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['financial_info']['tin_no'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Bank Name</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['financial_info']['bank']['name'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Bank Account No</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['financial_info']['account_no'] ?  : 'N/A' }}</td>
    </tr>
    </tbody>

</table>

<table class="tableHead" style="width: 100%;margin-top: 24px">

    <thead>
    <tr class="tableHeadRegular" style="background: #f8f8fb; width: 100%">
        <td style="width:15%;font-size: 12px; opacity: 0.8; font-weight: bold; font-family: 'Poppins', sans-serif; padding: 10px;border-bottom: solid 1px #d2d8e6;">
            Emergency
        </td>
        <td style="width:3%;border-bottom: solid 1px #d2d8e6;">

        </td>
        <td style="width:82%;border-bottom: solid 1px #d2d8e6;">

        </td>
    </tr>
    </thead>

    <tbody>
    <tr>
        <td class="tableRowValue" style="width: 15%">Name</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['emergency_info']['emergency_contract_person_name'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Mobile Number</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['emergency_info']['emergency_contract_person_number'] ?  : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="tableRowValue" style="width: 15%">Relationship</td>
        <td class="tableRowValue" style="width:3%">:</td>
        <td class="tableRowValue" style="width: 82%">{{ $employee['emergency_info']['emergency_contract_person_relationship'] ?  : 'N/A' }}</td>
    </tr>
    </tbody>

</table>

</body>

</html>
