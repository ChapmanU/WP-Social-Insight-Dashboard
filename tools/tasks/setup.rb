namespace :setup do

  ##################################
  desc 'Creates a WP db'
  ##################################
  task :files do
    print_step('Downloading and configuring WordPress core and test files')

    system "bash tests/bin/install-wp-tests.sh \
      #{$options['db_name_for_test']} \
      #{$options['db_username']} \
      #{$options['db_password']} \
      #{$options['db_host']} \
      #{$options['wp_version']}
    "

  end

  ##################################
  desc 'Creates a WP db'
  ##################################
  task :dev_db => :requires_wpcli do
    print_step('Creating WP dev db')

    # Check if WP is already installed
    if system "if ! $(#{@wpcli} core is-installed); then \nexit 1 \nfi"
      
      system "mysqladmin -f drop #{$options['db_name_for_dev']} \
        --user=#{$options['db_username']} \
        --password=#{$options['db_password']}"

    end

    # Create empty dev DB
    system "mysqladmin create #{$options['db_name_for_dev']} \
      --user=#{$options['db_username']} \
      --password=#{$options['db_password']}"

  end


  ##################################
  desc 'Install WordPress into DB'
  ##################################
  task :install_wp, [:is_multisite, :site_url] => :requires_wpcli do |t, args|
    args.with_defaults(:is_multisite => false, :site_url => $options['dev_url'])

    print_step("Install WordPress into DB, multisite=#{args.is_multisite}")

    install_mode = args.is_multisite ? 'multisite-install' : 'install'

    # Fill DB with stuff
    system "#{@wpcli} core #{install_mode} \
      --url=http://#{args['site_url']} \
      --title='SMT test site' \
      --admin_user=#{$options['wp_user']} \
      --admin_password=#{$options['wp_password']} \
      --admin_email=#{$options['wp_email']}"
  end


  ##################################
  desc 'Create wp-config for dev'
  ##################################
  task :wp_config, [:is_multisite, :db_name] => :requires_wpcli do |t, args|
    args.with_defaults(:is_multisite => false, :db_name => $options['db_name_for_dev'])

    print_step('Creating wp-config')

    `rm -f tmp/wp-config.php`
    `rm -f tmp/wordpress/wp-config.php`

    wp_params = "--skip-salts"
    wp_params << " --dbname=#{args['db_name']}"
    wp_params << " --dbuser=#{$options['db_username']}"
    wp_params << " --dbpass=#{$options['db_password']}" if !$options['db_password'].empty?
    wp_params << " --dbhost=#{$options['db_host']}"

    system "#{@wpcli} core config #{wp_params}"

    system "mv tmp/wordpress/wp-config.php tmp/wp-config.php"
  end

  desc 'Installs the SMT plugin as a symlink'
  task :install_plugin, [:is_multisite] => :requires_wpcli do |t, args|
    args.with_defaults(:is_multisite => false)

    print_step("Activating SMT plugin on DEV, multisite=#{args.is_multisite}")

    plugin_dir = 'tmp/wordpress/wp-content/plugins/social-metrics-tracker'

    # Symlink to ./src
    system "ln -s ../../../../src #{plugin_dir}"
    puts "Created a symbolic link at #{plugin_dir}"

    # Activate plugin
    if args.is_multisite
      system "#{@wpcli} plugin activate social-metrics-tracker --network"
    else
      system "#{@wpcli} plugin activate social-metrics-tracker"
    end
  end

end


##################################
desc 'Downloads and configures WordPress and test suite'
##################################
task :setup, [:quiet] do |t, args|
  args.with_defaults(:quiet => false)

  is_multisite = (args.quiet) ? false : confirm('Do you want to develop on WordPress multisite right now?')

  Rake::Task["setup:dev_db"].invoke
  Rake::Task["setup:files"].invoke
  Rake::Task["setup:wp_config"].invoke
  Rake::Task["setup:install_wp"].invoke(is_multisite)
  Rake::Task["setup:install_plugin"].invoke(is_multisite)

  print_success "Setup done! Run 'rake serve' to spin up a server!"
end