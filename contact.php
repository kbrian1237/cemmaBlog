<?php
$page_title = "Contact";
include 'includes/header.php';
?>

<section class="hero">
  <div class="container">
    <h1>Contact Us</h1>
    <p>We’d love to hear from you. Whether it’s feedback, ideas, or just to say hello — Cemma is here to listen.</p>
  </div>
</section>

<div class="container">
  <section class="contact-section">
    <h2>Send a Message</h2>
    <form action="process_message.php" method="POST" class="contact-form" id="contact-form">
      <input type="hidden" name="message_type" value="contact_form">
      <input type="hidden" name="priority" value="1">
      
      <div class="form-group">
        <label for="name">Your Name</label>
        <input type="text" name="sender_name" id="name" required class="form-input">
      </div>
      
      <div class="form-group">
        <label for="email">Your Email</label>
        <input type="email" name="sender_email" id="email" required class="form-input">
      </div>

      <div class="form-group">
        <label for="subject">Subject (Optional)</label>
        <input type="text" name="subject" id="subject" class="form-input">
      </div>

      <div class="form-group">
        <label for="message">Your Message</label>
        <textarea name="message_content" id="message" rows="6" required class="form-textarea"></textarea>
      </div>

      <button type="submit" class="btn btn-primary">Send Message</button>
    </form>
    <div id="contact-message-status" class="mt-3"></div>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contact-form');
    const messageStatusDiv = document.getElementById('contact-message-status');

    contactForm.addEventListener('submit', async function(e) {
        e.preventDefault(); // Prevent default form submission

        const formData = new FormData(contactForm);

        // Client-side validation (basic)
        const name = formData.get('sender_name').trim();
        const email = formData.get('sender_email').trim();
        const messageContent = formData.get('message_content').trim();

        if (!name || !email || !messageContent) {
            messageStatusDiv.innerHTML = '<div class="alert alert-error">Please fill in all required fields.</div>';
            return;
        }
        if (!/^[\w.-]+@([\w-]+\.)+[\w-]{2,4}$/.test(email)) {
            messageStatusDiv.innerHTML = '<div class="alert alert-error">Please enter a valid email address.</div>';
            return;
        }

        messageStatusDiv.innerHTML = '<div class="alert alert-info">Sending message...</div>';

        try {
            const response = await fetch('process_message.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                messageStatusDiv.innerHTML = '<div class="alert alert-success">Your message has been sent successfully!</div>';
                contactForm.reset(); // Clear the form
            } else {
                messageStatusDiv.innerHTML = `<div class="alert alert-error">Failed to send message: ${result.message || 'Unknown error.'}</div>`;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            messageStatusDiv.innerHTML = '<div class="alert alert-error">An error occurred. Please try again later.</div>';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
