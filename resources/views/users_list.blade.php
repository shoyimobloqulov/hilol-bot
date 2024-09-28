<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Foydalanuvchilar jadvali</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
</head>
<body>
<table class="table">
    <thead>
    <tr>
        <th scope="col">#</th>
        <th scope="col">Foydalanuvchi</th>
        <th scope="col">Viloyat / Tuman</th>
        <th scope="col">Maktab</th>
        <th scope="col">Tel no’mer(Telegram/Mobile)</th>
    </tr>
    </thead>
    <tbody>
    @php
        $k = 1;
    @endphp
    @foreach($users as $a)
        @if(strlen($a?->first_name) > 0 && strlen($a?->last_name) > 0
            && strlen($a?->regions) > 0 && strlen($a?->districts) > 0)
        <tr>
            <th scope="row">{{ $k }}</th>
            <td>{{ $a?->first_name . " " .$a?->last_name  }}</td>
            <td>{{ $a?->regions }} / {{ $a?->districts }}</td>
            <td>{{ $a?->schools }}</td>
            <td>{{ $a?->phone }} / {{ $a?->second_phone }}</td>
            @php
                $k ++;
            @endphp
        </tr>
        @endif
    @endforeach
    </tbody>
</table>

<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
</body>
</html>
