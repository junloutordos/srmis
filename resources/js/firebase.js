// resources/js/firebase.js
import { initializeApp } from "firebase/app";
import { getAuth, GoogleAuthProvider, signInWithPopup } from "firebase/auth";

const firebaseConfig = {
  apiKey:            import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain:        import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId:         import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket:     import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId:             import.meta.env.VITE_FIREBASE_APP_ID,
  measurementId:     import.meta.env.VITE_FIREBASE_MEASUREMENT_ID,
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const provider = new GoogleAuthProvider();

// Restrict Google account chooser to the configured domain
provider.setCustomParameters({
  // hd narrows Google's account chooser to ONE domain; with multiple
  // allowed domains (campuses + OED) we leave the chooser open and rely on
  // the server-side domain check instead.
  ...(String(import.meta.env.VITE_ALLOWED_EMAIL_DOMAIN || '').includes(',')
    ? {}
    : { hd: import.meta.env.VITE_ALLOWED_EMAIL_DOMAIN || 'pshs.edu.ph' })
});

export { auth, provider, signInWithPopup };
