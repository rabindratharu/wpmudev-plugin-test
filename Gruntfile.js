module.exports = function (grunt) {
	// Load all grunt tasks automatically
	require('load-grunt-tasks')(grunt);

	// Read composer.json to get required dependencies
	let requiredDeps = [];
	try {
		const composer = grunt.file.readJSON('composer.json');
		requiredDeps = Object.keys(composer.require || {})
			.filter(dep => dep !== 'php') // Exclude 'php' as it’s not a vendor package
			.map(dep => `vendor/${dep}/**`); // Map to vendor dependency paths
	} catch (e) {
		grunt.log.error('Error reading composer.json: ' + e.message);
		return false; // Exit if composer.json is missing or invalid
	}

	// Base files to copy (excluding vendor initially)
	const copyFiles = [
		'assets/**',
		'app/**',
		'core/**',
		'languages/**',
		'uninstall.php',
		'wpmudev-plugin-test.php',
		'!**/*.map', // Exclude source maps
		'!**/.DS_Store', // Exclude macOS metadata
		'!**/*.tmp', // Exclude temporary files
	];

	// Files for pro version (includes changelog.txt)
	const excludeCopyFilesPro = copyFiles
		.slice(0)
		.concat([
			'changelog.txt',
			'vendor/autoload.php', // Include Composer autoloader
			'vendor/composer/**', // Include Composer autoloader logic
			...requiredDeps, // Include required dependency folders
			'!vendor/**/tests/**', // Exclude test folders
			'!vendor/**/docs/**', // Exclude documentation
			'!vendor/**/.git/**', // Exclude .git folders
			'!vendor/**/test/**', // Exclude additional test folders
			'!vendor/**/examples/**', // Exclude example folders
		]);

	// Project configuration
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		// Clean task to remove temporary files and previous builds
		clean: {
			temp: {
				src: ['**/*.tmp', '**/.afpDeleted*', '**/.DS_Store'],
				dot: true,
				filter: 'isFile',
			},
			assets: ['build/assets/css/**', 'build/assets/js/**'],
			folder_v2: ['build/**'],
		},

		// Check text domain for WordPress i18n
		checktextdomain: {
			options: {
				text_domain: 'wpmudev-plugin-test',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d',
				],
			},
			files: {
				src: [
					'app/templates/**/*.php',
					'core/**/*.php',
					'!core/external/**', // Exclude external libs
					'google-analytics-async.php',
				],
				expand: true,
			},
		},

		// Copy task for pro version
		copy: {
			pro: {
				src: excludeCopyFilesPro,
				dest: 'build/<%= pkg.name %>/',
			},
		},

		// Compress task to create ZIP
		compress: {
			pro: {
				options: {
					mode: 'zip',
					archive: './build/<%= pkg.name %>-<%= pkg.version %>.zip',
				},
				expand: true,
				cwd: 'build/<%= pkg.name %>/',
				src: ['**/*'],
				dest: '<%= pkg.name %>/',
			},
		},
	});

	// Load grunt-search plugin (if used)
	grunt.loadNpmTasks('grunt-search');

	// Register tasks
	grunt.registerTask('version-compare', ['search']);
	grunt.registerTask('finish', function () {
		const json = grunt.file.readJSON('package.json');
		const file = `./build/${json.name}-${json.version}.zip`;
		grunt.log.writeln(`Process finished. ZIP created: ${file}`);
		grunt.log.writeln('----------');
	});

	// Build task
	grunt.registerTask('build', [
		'checktextdomain',
		'copy:pro',
		'compress:pro',
		'finish',
	]);

	// Pre-build clean task
	grunt.registerTask('preBuildClean', [
		'clean:temp',
		'clean:assets',
		'clean:folder_v2',
	]);

	// Default task
	grunt.registerTask('default', ['preBuildClean', 'build']);
};