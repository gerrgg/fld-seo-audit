const handleSEOAuditForm = () => {
  const root = document.querySelector('#fld-seo-audit');

  if( root ){
    const forms = Array.from(document.querySelectorAll('.fld-submit-alt-text-update'));

    forms.forEach(form => {
      const button = form.querySelector('button');

      if(button){
        button.addEventListener('click', (e) => {
          e.preventDefault();
          const altText = form.querySelector('input[name="alt_text"]').value;
          const imageId = form.querySelector('input[name="image_id"]').value;

          jQuery.ajax({
            url: updateAltText.ajax_url,
            type: 'POST',
            data: {
                action: 'fld_seo_audit_update_alt_text',
                nonce: updateAltText.nonce,
                image_id: imageId,
                alt_text: altText
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while updating the alt text.');
            }
          });
        })
      }
    })
  }
}

handleSEOAuditForm();

const handleExportAsCSV = () => {
  const exportButton = document.querySelector('#export-results-as-csv');
  if( exportButton ){

    exportButton.addEventListener('click', () => {
      const data = JSON.parse(exportButton.dataset.json);

      jQuery.ajax({
        url: updateAltText.ajax_url,
        type: 'POST',
        data: {
          action: 'fld_seo_audit_export_as_csv',
          nonce: updateAltText.nonce,
          csvData: data
        },
        success: function(response) {
             // Create a temporary link element
             var a = document.createElement('a');
             var blob = new Blob([response], { type: 'text/csv' });
             var url = window.URL.createObjectURL(blob);
             a.href = url;
             a.download = `floodlight-seo-audit-${Date.now()}.csv`; // The name of the downloaded file
             document.body.appendChild(a);
             a.click();
             window.URL.revokeObjectURL(url);
        },
        error: function() {
            alert('An error occurred while generating the CSV.');
        }
      });
    })
  }
}

handleExportAsCSV();