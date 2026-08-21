<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Several columns were made globally unique before multi-branch existed:
     * a plan's name/code, an enquiry's email, a member's email, and an
     * invoice number. Each of those is meant to be unique per branch, not
     * across every branch — most urgently invoice numbers, since sequence
     * generation now restarts per gym and two branches' first invoice would
     * otherwise collide on the same global unique index.
     *
     * `users.email` is deliberately left alone: login resolves a user by
     * email globally before branch context is known (see AuthController),
     * so it stays a single, install-wide unique constraint.
     *
     * `members.code` had no uniqueness constraint at all before this
     * migration (it was only ever kept unique by the sequence generator's
     * own logic, never enforced by the schema); this adds one, scoped per
     * branch, so the same race that affects invoice numbers can no longer
     * produce two members with the same code either.
     *
     * Note for upgrading an existing install with real data: if any branch
     * already has duplicate member codes (possible precisely because
     * nothing enforced uniqueness before), this migration will fail on
     * that constraint. A fresh install is unaffected.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropUnique('plans_name_unique');
            $table->dropUnique('plans_code_unique');
            $table->unique(['gym_id', 'name']);
            $table->unique(['gym_id', 'code']);
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->dropUnique('members_email_unique');
            $table->unique(['gym_id', 'email']);
            $table->unique(['gym_id', 'code']);
        });

        Schema::table('enquiries', function (Blueprint $table): void {
            $table->dropUnique('enquiries_email_unique');
            $table->unique(['gym_id', 'email']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_number_unique');
            $table->unique(['gym_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['gym_id', 'number']);
            $table->unique('number');
        });

        Schema::table('enquiries', function (Blueprint $table): void {
            $table->dropUnique(['gym_id', 'email']);
            $table->unique('email');
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->dropUnique(['gym_id', 'code']);
            $table->dropUnique(['gym_id', 'email']);
            $table->unique('email');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropUnique(['gym_id', 'code']);
            $table->dropUnique(['gym_id', 'name']);
            $table->unique('code');
            $table->unique('name');
        });
    }
};
