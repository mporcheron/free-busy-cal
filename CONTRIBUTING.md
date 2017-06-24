# Contributing

Contributions are welcome (and _encouraged_). If there is a bug you would like fixed, or a feature you would like 
implemented, it is much more useful to make a [pull request](https://github.com/mporcheron/FreeBusyCal/pulls) that does
this. However, if you are unable (or feel unable) to do this, then opening a ticket on the
 (Issue tracker)[https://github.com/mporcheron/FreeBusyCal/issues] is the way to go.

Please don't send emails relating to issues or feature requests, they are private, chaotic, and are untrackable. Send 
emails if you have questions relating to contributing, or other matters.

## Pull Request Process

1. Fork the main repository to your own Git, and create a new branch for your bug fixes or enhancements
2. Ensure you use composer-based libraries only (this allows for easier maintenance of your work in the future)
3. Update CHANGELOG with your changes. A new bullet at the top of the file signifies changes that have not been compiled
   into a release yet.
4. Be consistent with coding styles, this code base uses 
   [PSR-2](https://docs.opnsense.org/development/guidelines/psr2.html). No code will be merged that does not comply with
   PSR-2. You should also ensure that comments are correct and comply (or exceed) the existing documentation standard.
5. Open your pull request, and explain what the code does in general. Do not explain detailed code changes, these can be 
   inferred from your commits.

## Copyright

The codebase is, on the whole, copyright Martin Porcheron. When you make a pull request, you may retain copyright of 
your changes if they are significant (i.e. functions, classes, files...) by including a copyright notice within the 
appropriate comment block. It is infeasible to retain copyright over individual lines of code or bugfixes. If you do not
include any copyright notice, it is assumed you not retaining your rights over the work.
