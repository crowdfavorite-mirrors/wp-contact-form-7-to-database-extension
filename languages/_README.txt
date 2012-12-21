Place language translation files in this directory (*.po and *.mo files).
Translation files should be named following this convention:

    contact-form-7-to-database-extension-ll_CC.po
    contact-form-7-to-database-extension-ll_CC.mo

Where:
    "ll"  is an ISO 639 two- or three-letter language code
            http://www.gnu.org/software/autoconf/manual/gettext/Language-Codes.html#Language-Codes

    "CC" is an ISO 3166 two-letter country code
            http://www.gnu.org/software/autoconf/manual/gettext/Country-Codes.html#Country-Codes

NOTE: Strings that decorate DataTable widgets use a different i18n file.
Look at the file: dt_i18n/README.txt for more information

How can I create a new translation file?
- Download and install Poedit from http://www.poedit.net/
- Run Poedit
- "File" menu, "New catalog from POT file..." and open the file from this directory:
        contact-form-7-to-database-extension.pot
- Enter your translation for each string
- Save
- Rename the files to follow the convention above
- Upload your files into this directory in your WordPress installation
- ** Send your files to the Plugin author so he can share them with others
        email: michael_d_simpson@gmail.com