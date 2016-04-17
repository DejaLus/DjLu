module.exports = function (grunt) {
  'use strict';

  var output = {
    js: '<%= pkg.name %>.js',
    jsmin: '<%= pkg.name %>.min.js',
    map: '<%= pkg.name %>.min.js.map',
    test: {
      js: '<%= pkg.name %>.test.js',
      jsmin: '<%= pkg.name %>.test.min.js',
      map: '<%= pkg.name %>.test.min.js.map'
    }
  };

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),

    includes: {
      files: {
        src: 'src/main.js',
        dest: output.js,
        options: {
          includeRegexp: /^\s*\/\/\s*import\s+['"]?([^'"]+)['"]?\s*$/,
          duplicates: false
        },
      },
      tests: {
        src: output.js,
        dest: output.test.js,
        options: {
          includeRegexp: /^\s*\/\/\s*importTest\s+['"]?([^'"]+)['"]?\s*$/,
          duplicates: false,
          includePath: 'src/'
        },
      },
    },
    uglify: {
      jsmin: {
        options: {
          mangle: true,
          compress: {},
          sourceMap: output.map
        },
        src: output.js,
        dest: output.jsmin
      },
      jsminTest: {
        options: {
          mangle: true,
          compress: {},
          sourceMap: output.test.map
        },
        src: output.test.js,
        dest: output.test.jsmin
      }
    },
    sed: {
      version: {
        pattern: '%VERSION%',
        replacement: '<%= pkg.version %>',
        path: [output.js, output.jsmin, output.test.js, output.test.jsmin]
      }
    },
    jshint: {
      source: {
        src: ['src/**/*.js','spec/**/*.js','Gruntfile.js'],
        options: {
          indent: 2,
          ignores: ['**/*.min.js']
        }
      }
    },
    watch: {
      scripts: {
        files: ['src/**/*.js'],
        tasks: ['build', 'copy']
      },
      /**
      jasmine_runner: {
        files: ['spec/** / *.js'],
        tasks: ['jasmine:specs:build']
      },
      tests: {
        files: ['src/** / *.js', 'spec/** / *.js'],
        tasks: ['test']
      },
      reload: {
        files: [output.js, 'web/js/'+output.js, output.jsmin, 'web/js/'+output.jsmin],
        options: {
          livereload: true
        }
      }*/
    },
    /*
    jasmine: {
      specs: {
        options: {
          display: "short",
          summary: true,
          specs:  "spec/*-spec.js",
          helpers: "spec/helpers/*.js",
          version: "2.0.0",
          outfile: "spec/unit.html",
          keepRunner: true
        },
        src: output.test.js
      },
      integration: {
        options: {
          display: "short",
          summary: true,
          specs:  "spec/*-spec.js",
          helpers: "spec/integrationHelpers/*.js",
          version: "2.0.0",
          outfile: "spec/integration.html",
          keepRunner: true
        },
        src: output.test.js
      },
      coverage:{
        src: '<%= jasmine.specs.src %>',
        options:{
          specs: '<%= jasmine.specs.options.specs %>',
          helpers: '<%= jasmine.specs.options.helpers %>',
          version: '<%= jasmine.specs.options.version %>',
          template: require('grunt-template-jasmine-istanbul'),
          templateOptions: {
            coverage: 'coverage/jasmine/coverage.json',
            report: [
              {
                type: 'html',
                options: {
                  dir: 'coverage/jasmine'
                }
              }
            ]
          }
        }
      }
    },
    */
    emu: {
      api: {
        src: output.js,
        dest: 'docs.md'
      }
    },
    toc: {
      api: {
        src: '<%= emu.api.dest %>',
        dest: '<%= emu.api.dest %>'
      }
    },
    markdown: {
      html: {
        src: '<%= emu.api.dest %>',
        dest: 'docs.html'
      },
      options: {markdownOptions: {highlight: 'manual'}}
    },
    copy: {
      'web': {
        files: [
          { expand: true,
            flatten: true,
            src: [output.js,
              output.jsmin,
              output.map],
            dest: '..'
          }
        ]
      }
    }
  });

  // These plugins provide necessary tasks.
  grunt.loadNpmTasks('grunt-contrib-connect');
  grunt.loadNpmTasks('grunt-contrib-copy');
  //grunt.loadNpmTasks('grunt-contrib-jasmine');
  grunt.loadNpmTasks('grunt-contrib-jshint');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-markdown');
  grunt.loadNpmTasks('grunt-sed');
  grunt.loadNpmTasks('grunt-includes');

  // custom tasks
  grunt.registerMultiTask('emu', 'Documentation extraction by emu.', function() {
    var emu = require('emu'),
      fs = require('fs'),
      srcFile = this.files[0].src[0],
      destFile = this.files[0].dest,
      source = grunt.file.read(srcFile);
    grunt.file.write(destFile, emu.getComments(source));
    grunt.log.writeln('File "' + destFile + '" created.');
  });
  grunt.registerMultiTask('toc', 'Generate a markdown table of contents.', function() {
    var marked = require('marked'),
      slugify = function(s) { return s.trim().replace(/[-_\s]+/g, '-').toLowerCase(); },
      srcFile = this.files[0].src[0],
      destFile = this.files[0].dest,
      source = grunt.file.read(srcFile),
      tokens = marked.lexer(source),
      toc = tokens.filter(function (item) {
        return item.type == "heading" && item.depth == 2;
      }).reduce(function(toc, item) {
        return toc + "  * [" + item.text + "](#" + slugify(item.text) + ")\n";
      }, "");

    grunt.file.write(destFile, "# API Reference\n\n" + toc +"\n\n"+ source);
    grunt.log.writeln('Added TOC to "' + destFile + '".');
  });
  /*
  grunt.registerTask('watch:jasmine', function () {
    grunt.config('watch', {
      options: { interrupt: true },
      runner: grunt.config('watch').jasmine_runner,
      scripts: grunt.config('watch').scripts
    });
    grunt.task.run('watch');
  });
  */

  // task aliases
  grunt.registerTask('build', ['jshint', 'includes', 'uglify', 'sed']);
  grunt.registerTask('docs', ['build', 'copy', 'emu', 'toc', 'markdown']);
  //grunt.registerTask('web', ['docs']);
  //grunt.registerTask('server', ['docs', 'jasmine:specs:build', 'connect:server', 'watch:jasmine']);
  //grunt.registerTask('test', ['docs', 'jasmine:specs']);
  //grunt.registerTask('testloop', ['test', 'watch:tests']);
  //grunt.registerTask('testintegr', ['docs', 'jasmine:integration']);
  //grunt.registerTask('testintegrloop', ['docs', 'jasmine:integration']);
  //grunt.registerTask('coverage', ['docs', 'jasmine:coverage']);
  grunt.registerTask('lint', ['build', 'jshint']);
  grunt.registerTask('default', ['build']);
};