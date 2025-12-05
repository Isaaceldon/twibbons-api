<?php

Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('photo_url')->nullable();
            $table->string('language', 10)->default('fr');
            $table->string('country', 10)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('email');
            $table->index('country');
            $table->index('created_at');
        });

 Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->string('category')->default('event'); // event, ngo, corporate, school, personal
            $table->enum('type', ['image', 'video'])->default('image');
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->string('qr_code_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('slug');
            $table->index('category');
            $table->index('type');
            $table->index('visibility');
            $table->index('is_active');
            $table->index('published_at');
            $table->index('created_at');
        });
Schema::create('ai_providers', function (Blueprint $table) {
           $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('api_endpoint');
            $table->text('api_key_encrypted');
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(1); // 1 = highest priority
            $table->unsignedInteger('rate_limit')->default(100); // requests per hour
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('is_active');
            $table->index('priority');
        });


 Schema::create('ai_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('campaign_id')->nullable()->constrained('campaigns')->onDelete('set null');
            $table->foreignUuid('ai_provider_id')->constrained('ai_providers')->onDelete('cascade');
            $table->text('prompt');
            $table->string('logo_path')->nullable();
            $table->json('parameters')->nullable(); // size, style, colors, etc.
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('results')->nullable(); // array of generated image URLs
            $table->decimal('generation_time', 8, 2)->nullable(); // seconds
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('ai_provider_id');
            $table->index('status');
            $table->index('created_at');
        });


    Schema::create('campaign_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->foreignUuid('ai_generation_id')->nullable()->constrained('ai_generations')->onDelete('set null');
            $table->enum('type', ['image', 'video'])->default('image');
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->json('metadata')->nullable(); // format, colors, elements, etc.
            $table->unsignedInteger('width')->default(1080);
            $table->unsignedInteger('height')->default(1080);
            $table->unsignedBigInteger('file_size')->default(0); // bytes
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('campaign_id');
            $table->index('ai_generation_id');
            $table->index('type');
            $table->index('is_primary');
            $table->index('is_active');
        });


Schema::create('user_generated_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null'); // nullable for guests
            $table->foreignUuid('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->foreignUuid('campaign_template_id')->constrained('campaign_templates')->onDelete('cascade');
            $table->string('photo_path'); // User's uploaded photo
            $table->string('result_path'); // Final twibbon with frame
            $table->json('customizations')->nullable(); // text, stickers, adjustments applied
            $table->string('device_type', 50)->nullable(); // mobile, desktop, tablet
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 10)->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('campaign_template_id');
            $table->index('country');
            $table->index('device_type');
            $table->index('created_at');
        });



         Schema::create('brand_kits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->json('color_palette')->nullable(); // ["#FF5733", "#33FF57", ...]
            $table->json('typography')->nullable(); // {primary: "Roboto", secondary: "Open Sans"}
            $table->json('patterns')->nullable(); // generated pattern images
            $table->json('metadata')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('is_default');
        });


         Schema::create('brand_kit_campaign', function (Blueprint $table) {
             $table->uuid('id')->primary();
            $table->foreignUuid('brand_kit_id')->constrained('brand_kits')->onDelete('cascade');
            $table->foreignUuid('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->json('applied_settings')->nullable(); // specific settings used for this campaign
            $table->timestamps();

            // Unique constraint
            $table->unique(['brand_kit_id', 'campaign_id']);

            // Indexes
            $table->index('brand_kit_id');
            $table->index('campaign_id');
        });



         Schema::create('video_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('video_path'); // Path to template video file
            $table->unsignedInteger('duration'); // Duration in seconds
            $table->enum('format', ['1:1', '9:16', '16:9'])->default('1:1');
            $table->unsignedInteger('width')->default(1080);
            $table->unsignedInteger('height')->default(1080);
            $table->json('overlay_zones')->nullable(); // Zones where logo/text can be placed
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('is_active');
            $table->index('format');
            $table->index('usage_count');
        });



        Schema::create('video_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('video_template_id')->constrained('video_templates')->onDelete('cascade');
            $table->string('logo_path')->nullable();
            $table->string('text')->nullable();
            $table->json('parameters')->nullable(); // Additional customization parameters
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('output_path')->nullable();
            $table->decimal('processing_time', 8, 2)->nullable(); // seconds
            $table->unsignedInteger('progress')->default(0); // 0-100
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('video_template_id');
            $table->index('status');
            $table->index('created_at');
        });


          Schema::create('shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_generated_image_id')->constrained('user_generated_images')->onDelete('cascade');
            $table->string('platform', 50); // facebook, twitter, instagram, linkedin, whatsapp
            $table->string('share_url')->nullable();
            $table->json('metadata')->nullable(); // platform-specific data
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 10)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_generated_image_id');
            $table->index('platform');
            $table->index('country');
            $table->index('created_at');
        });



        Schema::create('campaign_statistics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->unique()->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_generations')->default(0);
            $table->unsignedBigInteger('total_downloads')->default(0);
            $table->unsignedBigInteger('total_shares')->default(0);
            $table->json('countries')->nullable(); // {"FR": 120, "US": 89, ...}
            $table->json('devices')->nullable(); // {"mobile": 200, "desktop": 150, ...}
            $table->json('platforms')->nullable(); // Share platforms breakdown
            $table->json('top_referrers')->nullable(); // Where traffic comes from
            $table->date('stats_date')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('campaign_id');
            $table->index('total_views');
            $table->index('total_generations');
            $table->index('stats_date');
        });



         Schema::create('statistics_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->date('stats_date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('generations')->default(0);
            $table->unsignedInteger('downloads')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->json('details')->nullable(); // Hourly breakdown, countries, devices
            $table->timestamps();

            // Unique constraint - one record per campaign per day
            $table->unique(['campaign_id', 'stats_date']);

            // Indexes
            $table->index('campaign_id');
            $table->index('stats_date');
            $table->index(['stats_date', 'campaign_id']);
        });


        Schema::create('global_statistics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('total_users')->default(0);
            $table->unsignedBigInteger('active_users')->default(0); // Last 30 days
            $table->unsignedBigInteger('total_campaigns')->default(0);
            $table->unsignedBigInteger('active_campaigns')->default(0);
            $table->unsignedBigInteger('total_generations')->default(0);
            $table->unsignedBigInteger('total_downloads')->default(0);
            $table->unsignedBigInteger('total_ai_generations')->default(0);
            $table->unsignedBigInteger('total_video_jobs')->default(0);
            $table->date('stats_date')->unique();
            $table->timestamps();

            // Indexes
            $table->index('stats_date');
        });



         Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('collection_name');
        });


         Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('read_at');
        });


         
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        throw_if($teams && empty($columnNames['team_foreign_key'] ?? null), Exception::class, 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            // $table->engine('InnoDB');
            $table->uuid('uuid')->primary()->unique(); // permission id
            $table->string('name');       // For MyISAM use string('name', 225); // (or 166 for InnoDB with Redundant/Compact row format)
            $table->string('guard_name'); // For MyISAM use string('guard_name', 25);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $columnNames) {
            // $table->engine('InnoDB');
            $table->uuid('uuid')->primary()->unique(); // role id
            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing
                $table->uuid($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->string('name');       // For MyISAM use string('name', 225); // (or 166 for InnoDB with Redundant/Compact row format)
            $table->string('guard_name'); // For MyISAM use string('guard_name', 25);
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
            $table->uuid($pivotPermission);

            $table->string('model_type');
            // $table->uuid($columnNames['model_morph_key']);
            $table->uuid($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('uuid') // permission uuid
                ->on($tableNames['permissions'])
                ->onDelete('cascade');
            if ($teams) {
                $table->uuid($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            } else {
                $table->primary(
                    [$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            }
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {
            $table->uuid($pivotRole);

            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('uuid') // role uuid
                ->on($tableNames['roles'])
                ->onDelete('cascade');
            if ($teams) {
                $table->uuid($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            } else {
                $table->primary(
                    [$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            }
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->uuid($pivotPermission);
            $table->uuid($pivotRole);

            $table->foreign($pivotPermission)
                ->references('uuid') // permission uuid
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('uuid') // role uuid
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
