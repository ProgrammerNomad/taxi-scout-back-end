<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">

<head>
    <meta charset="utf-8" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta http-equiv="x-pjax-version" content="{{ mix('/css/app.css') }}">
    <title>{{ app_name() ?? 'Tagxi' }} - Admin App</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta content="Tag your taxi Admin portal, helps to manage your fleets and trip requests" name="description" />
    <meta content="Coderthemes" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <meta name="theme-color" content="#0B4DD8">


    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ fav_icon() ?? asset('assets/images/favicon.ico')}}">
    <style>
        p.notify001 {
            color: #fff;
            background-color: #e62525;
            width: 1.5vw;
            height: 3vh;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 41px;
            font-size: 12px;
            position: absolute;
            top: 0px;
            left: 32px;
        }
    </style>

    @include('admin.layouts.common_styles')

    @yield('extra-css')
</head>

<body class="hold-transition skin-blue sidebar-mini fixed">
    <!-- Begin page -->
    <div class="wrapper skin-blue-light">
        <!-- Navigation -->
        @include('admin.layouts.topnavbar')

        @include('admin.layouts.navigation')

        <div class="content-wrapper">
            <!-- Page wrapper -->
            @include('admin.layouts.common_scripts')

            <!-- Main view  -->
            @yield('content')

        </div>
        <!-- Footer -->

    </div>

    @yield('extra-js')

    <script>
        $(".is_view").click(function() {
            var target = $(this).data('target');
            console.log(target);
            $.ajax({
                url: 'chat/seen',
                type: 'post',
                data: {
                    _token: '{{ csrf_token() }}',
                    chat_id: target,
                },
                success: function(response) {
                    console.log(response);
                    // $('#countShow').append(response);
                },
                error: function(xhr, status, error) {
                    console.log('An error occurred: ' + error);
                }
            });
        });
    </script>

</body>

</html>
