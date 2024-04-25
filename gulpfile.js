var gulp = require('gulp');
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');

//script paths
var jsDest = 'assets/js';

gulp.task('magic', function () {
    return gulp.src(['assets/js/src/sourcebuster.js', 'assets/js/src/tippy.min.js', 'assets/js/src/metorik.js'])
        .pipe(concat('metorik.min.js'))
        .pipe(gulp.dest(jsDest))
        .pipe(uglify())
        .pipe(gulp.dest(jsDest));
});