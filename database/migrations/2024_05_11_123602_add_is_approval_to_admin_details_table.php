<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsApprovalToAdminDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('admin_details', function (Blueprint $table) {
            $table->string('is_approval')->default(0)->nullable()->after('created_by');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('company_key')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admin_details', function (Blueprint $table) {
            $table->dropColumn('is_approval');
        });
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('company_key');
        });
    }
}
