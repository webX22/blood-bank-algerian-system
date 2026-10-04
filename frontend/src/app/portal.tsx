"use client";

import { type FormEvent, useEffect, useMemo, useRef, useState } from "react";

type Locale = "ar" | "fr" | "en";
type Role = "donor" | "recipient";

type User = {
  id: number;
  name: string;
  email: string;
  role: Role | "staff" | "admin";
  donor?: {
    consent_to_contact: boolean;
    availability: string;
    account_verified: boolean;
    medical_eligibility_verified: boolean;
    blood_type_id: number | null;
    wilaya_id: number;
  } | null;
};

type RequestRecord = {
  id: number;
  status: string;
  urgency: string;
  units: number;
  fulfilled_units: number;
  needed_by: string;
  blood_type?: { code: string };
  component?: { code: string };
  recipient?: { first_name: string; last_name: string; phone: string };
  facility?: { name: string };
};

type InventoryRecord = {
  facility_name: string;
  blood_type: string;
  component: string;
  units: number;
};

type DonorCandidate = {
  id: number;
  first_name: string;
  last_name: string;
  phone: string;
  blood_type: string;
};

type DonorReviewRecord = {
  id: number;
  first_name: string;
  last_name: string;
  blood_type: string | null;
  wilaya: { code: string; name_fr: string };
  availability: string;
  account_verified: boolean;
  medical_eligibility_verified: boolean;
  consent_to_contact: boolean;
};

type OperationsData = {
  requests_by_status: Record<string, number>;
  verified_facilities: number;
  available_units: number;
  eligible_donor_profiles: number;
};

type ReferenceData = {
  blood_types: { id: number; code: string; name: string }[];
  components: { id: number; code: string; name: string }[];
  wilayas: {
    id: number;
    code: string;
    name_ar: string;
    name_fr: string;
    name_en: string;
  }[];
  communes_available: boolean;
  geographic_dataset_notice: string;
};

type ApiPayload = {
  message?: string;
  errors?: Record<string, string[]>;
  data?: unknown;
  user?: User;
  token?: string;
  request?: { id: number; status: string };
  donor?: NonNullable<User["donor"]>;
};

const apiBase =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";

const messages = {
  en: {
    eyebrow: "A considered network for blood donation",
    title: "When every minute matters, start with a trusted connection.",
    intro:
      "A shared place for donors, people requesting blood, and healthcare teams to coordinate—with people and patient safety at the centre.",
    donate: "Become a donor",
    request: "Request blood",
    network: "A clearer path from need to care",
    networkText:
      "Requests are reviewed by authorized staff before donor matching or inventory fulfillment.",
    donorsTitle: "For donors",
    donorsText:
      "Register your area and contact preference. Your profile stays unverified and unavailable for matching until reviewed.",
    requestsTitle: "For recipients",
    requestsText:
      "Submit a request with type, component, urgency and location. A healthcare team must review it.",
    teamsTitle: "For care teams",
    teamsText:
      "Review requests, record donations and track available units. Red-cell matching is an aid, never a clinical decision.",
    locations: "Wilayas listed",
    locationsNote: "Provisional labels · commune data not loaded",
    apiOnline: "Service connected",
    apiOffline: "Service not connected",
    apiChecking: "Connecting to the service…",
    selectLanguage: "Language",
    account: "Get started",
    register: "Create account",
    signIn: "Sign in",
    donor: "Donor",
    recipient: "Recipient",
    fullName: "Display name",
    firstName: "First name",
    lastName: "Last name",
    email: "Email",
    phone: "Phone",
    password: "Password (12 characters minimum)",
    wilaya: "Wilaya",
    chooseWilaya: "Choose a wilaya",
    bloodType: "Blood group (if known)",
    unknown: "Not known",
    contactConsent:
      "I agree to be contacted about donation opportunities. My contact details will only be shown to authorized staff.",
    create: "Create account",
    continue: "Sign in",
    signOut: "Sign out",
    welcome: "You’re signed in",
    donorPending:
      "Your donor profile is private and cannot be matched until authorized staff verify it and confirm eligibility.",
    recipientReady:
      "You can submit a request below. It will remain under review until an authorized healthcare team approves it.",
    requestTitle: "Blood request",
    component: "Component",
    units: "Units",
    urgency: "Urgency",
    routine: "Routine",
    urgent: "Urgent",
    emergency: "Emergency",
    neededBy: "Needed by",
    submitRequest: "Submit for review",
    requestSubmitted: "Request submitted for healthcare review.",
    working: "Working…",
    networkError:
      "Could not reach the API. Start the backend and check NEXT_PUBLIC_API_BASE_URL and CORS_ALLOWED_ORIGINS.",
    noWilayas: "Location choices load when the API is available.",
    disclaimer:
      "This is a technical coordination platform, not medical advice or a substitute for transfusion services. All donor eligibility, compatibility and clinical decisions require qualified healthcare professionals.",
    footer: "Built for coordination. Not yet authorized for clinical deployment.",
    required: "Required",
    contactPreference: "Donor contact preferences",
    donorContactConsent: "Allow authorized healthcare staff to contact me about donation requests.",
    donorAvailability: "Donation availability",
    available: "Available to be contacted",
    unavailable: "Not available",
    donorEligibilityNote:
      "You can choose contact consent and availability. Only authorized healthcare staff can verify your account, blood group, and medical eligibility.",
    accountVerification: "Account verified",
    medicalReview: "Medical eligibility reviewed",
    savePreferences: "Save donor preferences",
    donorsToReview: "Donor profiles for review",
    reviewDonor: "Confirm staff review and availability",
    staffReviewWarning:
      "Only confirm account identity and eligibility after your facility's required in-person checks. The platform does not perform medical screening.",
    noDonorsToReview: "No donor profiles are available in your assigned area.",
  },
  fr: {
    eyebrow: "Un réseau réfléchi pour le don de sang",
    title: "Quand chaque minute compte, commençons par un lien de confiance.",
    intro:
      "Un espace partagé pour les donneurs, les personnes qui demandent du sang et les équipes de santé, avec la sécurité au premier plan.",
    donate: "Devenir donneur",
    request: "Demander du sang",
    network: "Un parcours plus clair, du besoin aux soins",
    networkText:
      "Toute demande est examinée par une équipe habilitée avant la mise en relation ou la sortie de stock.",
    donorsTitle: "Pour les donneurs",
    donorsText:
      "Indiquez votre région et vos préférences de contact. Votre profil reste privé et non éligible aux mises en relation avant vérification.",
    requestsTitle: "Pour les patients",
    requestsText:
      "Précisez le groupe, le composant, l'urgence et la région. Une équipe de santé doit examiner la demande.",
    teamsTitle: "Pour les équipes de soins",
    teamsText:
      "Examinez les demandes, enregistrez les dons et suivez les unités disponibles. La compatibilité des globules rouges reste une aide, jamais une décision clinique.",
    locations: "Wilayas répertoriées",
    locationsNote: "Libellés provisoires · communes non chargées",
    apiOnline: "Service connecté",
    apiOffline: "Service non connecté",
    apiChecking: "Connexion au service…",
    selectLanguage: "Langue",
    account: "Commencer",
    register: "Créer un compte",
    signIn: "Se connecter",
    donor: "Donneur",
    recipient: "Patient",
    fullName: "Nom affiché",
    firstName: "Prénom",
    lastName: "Nom",
    email: "E-mail",
    phone: "Téléphone",
    password: "Mot de passe (12 caractères minimum)",
    wilaya: "Wilaya",
    chooseWilaya: "Choisir une wilaya",
    bloodType: "Groupe sanguin (si connu)",
    unknown: "Inconnu",
    contactConsent:
      "J'accepte d'être contacté au sujet de possibilités de don. Mes coordonnées ne seront visibles que par le personnel autorisé.",
    create: "Créer le compte",
    continue: "Se connecter",
    signOut: "Se déconnecter",
    welcome: "Vous êtes connecté",
    donorPending:
      "Votre profil donneur reste privé et ne peut être proposé avant sa vérification par le personnel habilité.",
    recipientReady:
      "Vous pouvez soumettre une demande ci-dessous. Elle restera en examen jusqu'à l'approbation d'une équipe de santé habilitée.",
    requestTitle: "Demande de sang",
    component: "Composant",
    units: "Unités",
    urgency: "Urgence",
    routine: "Normale",
    urgent: "Urgente",
    emergency: "Urgence vitale",
    neededBy: "Souhaité pour",
    submitRequest: "Soumettre pour examen",
    requestSubmitted: "Demande soumise à l'équipe de santé.",
    working: "En cours…",
    networkError:
      "API inaccessible. Démarrez le backend et vérifiez NEXT_PUBLIC_API_BASE_URL et CORS_ALLOWED_ORIGINS.",
    noWilayas: "Les choix de région seront chargés lorsque l'API sera disponible.",
    disclaimer:
      "Cette plateforme facilite la coordination ; elle ne fournit pas de conseil médical et ne remplace pas les services transfusionnels. L'éligibilité, la compatibilité et les décisions cliniques relèvent de professionnels de santé qualifiés.",
    footer: "Conçu pour la coordination. Pas encore autorisé au déploiement clinique.",
    required: "Obligatoire",
    contactPreference: "Préférences de contact du donneur",
    donorContactConsent: "Autoriser le personnel de santé habilité à me contacter au sujet des demandes de don.",
    donorAvailability: "Disponibilité pour le don",
    available: "Disponible pour être contacté",
    unavailable: "Indisponible",
    donorEligibilityNote:
      "Vous pouvez choisir votre consentement et votre disponibilité. Seul le personnel de santé habilité peut vérifier votre identité, votre groupe sanguin et votre éligibilité médicale.",
    accountVerification: "Compte vérifié",
    medicalReview: "Éligibilité médicale examinée",
    savePreferences: "Enregistrer mes préférences",
    donorsToReview: "Profils donneurs à examiner",
    reviewDonor: "Confirmer la vérification et la disponibilité",
    staffReviewWarning:
      "Confirmez l'identité et l'éligibilité uniquement après les vérifications requises par votre établissement. La plateforme n'effectue aucun examen médical.",
    noDonorsToReview: "Aucun profil donneur dans votre secteur attribué.",
  },
  ar: {
    eyebrow: "شبكة مدروسة للتبرع بالدم",
    title: "عندما تكون كل دقيقة مهمة، ابدأ بتواصل موثوق.",
    intro:
      "مساحة مشتركة للمتبرعين وطالبي الدم وفرق الرعاية الصحية، مع وضع سلامة الأشخاص والمرضى في المقام الأول.",
    donate: "سجّل كمتبرع",
    request: "اطلب الدم",
    network: "مسار أوضح من الحاجة إلى الرعاية",
    networkText:
      "تُراجع الطلبات من طرف طاقم مخوّل قبل مطابقة المتبرعين أو صرف المخزون.",
    donorsTitle: "للمتبرعين",
    donorsText:
      "سجّل منطقتك وطريقة التواصل المفضلة. يبقى ملفك غير مؤهل للمطابقة حتى التحقق منه.",
    requestsTitle: "لطالبي الدم",
    requestsText:
      "قدّم طلباً يحدد الفصيلة والمكوّن والاستعجال والمنطقة. يجب أن يراجعه فريق صحي.",
    teamsTitle: "لفرق الرعاية",
    teamsText:
      "راجع الطلبات وسجّل التبرعات وتابع الوحدات المتاحة. مطابقة الكريات الحمراء مساعدة وليست قراراً طبياً.",
    locations: "ولايات مدرجة",
    locationsNote: "تسميات أولية · بيانات البلديات غير محمّلة",
    apiOnline: "الخدمة متصلة",
    apiOffline: "الخدمة غير متصلة",
    apiChecking: "جارٍ الاتصال بالخدمة…",
    selectLanguage: "اللغة",
    account: "ابدأ الآن",
    register: "إنشاء حساب",
    signIn: "تسجيل الدخول",
    donor: "متبرع",
    recipient: "طالب دم",
    fullName: "الاسم المعروض",
    firstName: "الاسم",
    lastName: "اللقب",
    email: "البريد الإلكتروني",
    phone: "الهاتف",
    password: "كلمة المرور (12 حرفاً على الأقل)",
    wilaya: "الولاية",
    chooseWilaya: "اختر الولاية",
    bloodType: "فصيلة الدم (إن عُرفت)",
    unknown: "غير معروفة",
    contactConsent:
      "أوافق على التواصل معي بشأن فرص التبرع. لا تظهر بيانات الاتصال إلا للموظفين المخولين.",
    create: "إنشاء الحساب",
    continue: "تسجيل الدخول",
    signOut: "تسجيل الخروج",
    welcome: "تم تسجيل الدخول",
    donorPending:
      "ملفك كمتبرع خاص ولا يمكن مطابقته حتى يتحقق منه الموظفون المخولون ويؤكدوا الأهلية.",
    recipientReady:
      "يمكنك تقديم طلب أدناه. سيبقى قيد المراجعة حتى توافق عليه جهة صحية مخولة.",
    requestTitle: "طلب دم",
    component: "المكوّن",
    units: "الوحدات",
    urgency: "الاستعجال",
    routine: "عادي",
    urgent: "مستعجل",
    emergency: "طارئ",
    neededBy: "الموعد المطلوب",
    submitRequest: "إرسال للمراجعة",
    requestSubmitted: "تم إرسال الطلب للمراجعة الصحية.",
    working: "جارٍ التنفيذ…",
    networkError:
      "تعذر الاتصال بواجهة API. شغّل الخادم وتحقق من NEXT_PUBLIC_API_BASE_URL و CORS_ALLOWED_ORIGINS.",
    noWilayas: "تُحمّل خيارات المناطق عند توفر واجهة API.",
    disclaimer:
      "هذه منصة تنسيق تقنية وليست نصيحة طبية ولا بديلاً عن خدمات نقل الدم. يجب أن يقيّم مختصون صحيون أهلية المتبرع والتوافق وجميع القرارات السريرية.",
    footer: "منصة للتنسيق. لم تُعتمد بعد للاستخدام السريري.",
    required: "مطلوب",
    contactPreference: "تفضيلات تواصل المتبرع",
    donorContactConsent: "أسمح للموظفين الصحيين المخولين بالتواصل معي بشأن طلبات التبرع.",
    donorAvailability: "مدى التوفر للتبرع",
    available: "متاح للتواصل",
    unavailable: "غير متاح",
    donorEligibilityNote:
      "يمكنك اختيار الموافقة على التواصل ومدى التوفر. وحدهم الموظفون الصحيون المخولون يمكنهم التحقق من الحساب والفصيلة والأهلية الطبية.",
    accountVerification: "تم التحقق من الحساب",
    medicalReview: "تمت مراجعة الأهلية الطبية",
    savePreferences: "حفظ تفضيلات المتبرع",
    donorsToReview: "ملفات المتبرعين للمراجعة",
    reviewDonor: "تأكيد مراجعة الموظفين والتوفر",
    staffReviewWarning:
      "لا تؤكد الهوية والأهلية إلا بعد الفحوصات المطلوبة لدى مؤسستك. المنصة لا تجري فحوصات طبية.",
    noDonorsToReview: "لا توجد ملفات متبرعين في منطقتك المعيّنة.",
  },
} satisfies Record<Locale, Record<string, string>>;

async function apiRequest(
  path: string,
  options: { method?: string; body?: unknown; token?: string } = {},
): Promise<ApiPayload> {
  const response = await fetch(`${apiBase}${path}`, {
    method: options.method ?? "GET",
    headers: {
      Accept: "application/json",
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
    cache: "no-store",
  });
  const payload = (await response.json()) as ApiPayload;

  if (!response.ok) {
    const validationMessage = payload.errors
      ? Object.values(payload.errors).flat().join(" ")
      : undefined;
    throw new Error(validationMessage ?? payload.message ?? "Request failed.");
  }

  return payload;
}

export default function Portal() {
  const [locale, setLocale] = useState<Locale>("fr");
  const [references, setReferences] = useState<ReferenceData | null>(null);
  const [connection, setConnection] = useState<"checking" | "ready" | "offline">(
    "checking",
  );
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [mode, setMode] = useState<"register" | "login">("register");
  const [role, setRole] = useState<Role>("donor");
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [requestNumber, setRequestNumber] = useState<number | null>(null);
  const [operations, setOperations] = useState<OperationsData | null>(null);
  const [operationsRequests, setOperationsRequests] = useState<RequestRecord[]>([]);
  const [inventory, setInventory] = useState<InventoryRecord[]>([]);
  const [matchedDonors, setMatchedDonors] = useState<Record<number, DonorCandidate[]>>({});
  const [donorsForReview, setDonorsForReview] = useState<DonorReviewRecord[]>([]);
  const neededByRef = useRef<HTMLInputElement>(null);
  const text = messages[locale];
  const direction = locale === "ar" ? "rtl" : "ltr";

  useEffect(() => {
    const input = neededByRef.current;
    if (user?.role !== "recipient" || !input) return;

    const now = new Date();
    input.min = now.toISOString().slice(0, 16);
    input.value = new Date(now.getTime() + 36 * 60 * 60 * 1000)
      .toISOString()
      .slice(0, 16);
  }, [user?.role]);

  useEffect(() => {
    const controller = new AbortController();

    async function loadReferences() {
      setConnection("checking");

      try {
        const response = await fetch(
          `${apiBase}/reference-data?locale=${locale}`,
          { cache: "no-store", signal: controller.signal },
        );
        if (!response.ok) throw new Error("Reference data unavailable.");
        setReferences((await response.json()) as ReferenceData);
        setConnection("ready");
      } catch {
        if (!controller.signal.aborted) setConnection("offline");
      }
    }

    void loadReferences();
    return () => controller.abort();
  }, [locale]);

  useEffect(() => {
    if (!token || !user || !["staff", "admin"].includes(user.role)) {
      return;
    }

    let active = true;
    async function loadOperations() {
      try {
        const [dashboardResult, requestsResult, inventoryResult, donorsResult] = await Promise.all([
          apiRequest("/operations/dashboard", { token: token! }),
          apiRequest("/blood-requests", { token: token! }),
          apiRequest("/operations/inventory", { token: token! }),
          apiRequest("/operations/donors", { token: token! }),
        ]);
        if (!active) return;
        setOperations(dashboardResult as OperationsData);
        setOperationsRequests(
          Array.isArray(requestsResult.data)
            ? (requestsResult.data as RequestRecord[])
            : [],
        );
        setInventory(
          Array.isArray(inventoryResult.data)
            ? (inventoryResult.data as InventoryRecord[])
            : [],
        );
        setDonorsForReview(
          Array.isArray(donorsResult.data)
            ? (donorsResult.data as DonorReviewRecord[])
            : [],
        );
        setError("");
      } catch (cause) {
        if (active) {
          setError(cause instanceof Error ? cause.message : text.networkError);
        }
      }
    }

    void loadOperations();
    return () => {
      active = false;
    };
  }, [token, user, text.networkError]);

  const wilayaOptions = useMemo(
    () =>
      (references?.wilayas ?? []).map((wilaya) => ({
        id: wilaya.id,
        code: wilaya.code,
        name:
          locale === "ar"
            ? wilaya.name_ar
            : locale === "en"
              ? wilaya.name_en
              : wilaya.name_fr,
      })),
    [locale, references],
  );

  async function submitAccount(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setError("");
    setMessage("");

    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    const payload =
      mode === "register"
        ? {
            role,
            name: form.get("name"),
            first_name: form.get("first_name"),
            last_name: form.get("last_name"),
            email: form.get("email"),
            phone: form.get("phone"),
            password: form.get("password"),
            password_confirmation: form.get("password"),
            locale,
            ...(role === "donor" && form.get("blood_type_id")
              ? { blood_type_id: Number(form.get("blood_type_id")) }
              : {}),
            ...(role === "donor" && form.get("consent_to_contact")
              ? { consent_to_contact: true }
              : {}),
            ...(form.get("wilaya_id")
              ? { wilaya_id: Number(form.get("wilaya_id")) }
              : {}),
          }
        : {
            email: form.get("email"),
            password: form.get("password"),
          };

    try {
      const result = await apiRequest(
        mode === "register" ? "/auth/register" : "/auth/login",
        { method: "POST", body: payload },
      );
      if (!result.user || !result.token) {
        throw new Error("The service returned an incomplete sign-in response.");
      }
      setUser(result.user);
      setToken(result.token);
      setMessage(text.welcome);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : text.networkError);
    } finally {
      setPending(false);
    }
  }

  async function submitBloodRequest(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!token) return;

    setPending(true);
    setError("");
    setMessage("");

    const formElement = event.currentTarget;
    const form = new FormData(formElement);

    try {
      const result = await apiRequest("/blood-requests", {
        method: "POST",
        token,
        body: {
          blood_type_id: Number(form.get("blood_type_id")),
          blood_component_id: Number(form.get("blood_component_id")),
          wilaya_id: Number(form.get("wilaya_id")),
          units: Number(form.get("units")),
          urgency: form.get("urgency"),
          needed_by: new Date(String(form.get("needed_by"))).toISOString(),
        },
      });
      setRequestNumber(result.request?.id ?? null);
      setMessage(text.requestSubmitted);
      formElement.reset();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : text.networkError);
    } finally {
      setPending(false);
    }
  }

  async function signOut() {
    if (token) {
      try {
        await apiRequest("/auth/logout", { method: "POST", token });
      } catch {
        setError(text.networkError);
        return;
      }
    }
    setToken(null);
    setUser(null);
    setOperations(null);
    setOperationsRequests([]);
    setInventory([]);
    setMatchedDonors({});
    setDonorsForReview([]);
    setMessage("");
  }

  async function reviewRequest(id: number, decision: "approve" | "reject") {
    if (!token) return;
    setPending(true);
    setError("");
    try {
      await apiRequest(`/operations/blood-requests/${id}/review`, {
        method: "POST",
        token,
        body: { decision },
      });
      setOperationsRequests((current) =>
        current.map((record) =>
          record.id === id
            ? { ...record, status: decision === "approve" ? "open" : "rejected" }
            : record,
        ),
      );
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : text.networkError);
    } finally {
      setPending(false);
    }
  }

  async function fulfillRequest(record: RequestRecord) {
    if (!token) return;
    setPending(true);
    setError("");
    try {
      const result = await apiRequest(
        `/operations/blood-requests/${record.id}/fulfill`,
        {
          method: "POST",
          token,
          body: { units: 1 },
        },
      );
      const updated = result.request as RequestRecord;
      setOperationsRequests((current) =>
        current.map((item) => (item.id === record.id ? { ...item, ...updated } : item)),
      );
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : text.networkError);
    } finally {
      setPending(false);
    }
  }

  async function findDonors(requestId: number) {
    if (!token) return;
    setPending(true);
    setError("");
    try {
      const result = await apiRequest(
        `/operations/blood-requests/${requestId}/matches`,
        { token },
      );
      const page = result.data as { data?: DonorCandidate[] } | undefined;
      setMatchedDonors((current) => ({
        ...current,
        [requestId]: page?.data ?? [],
      }));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : text.networkError);
    } finally {
      setPending(false);
    }
  }

  async function saveDonorPreferences(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!token) return;
    setPending(true);
    setError("");
    setMessage("");
    const form = new FormData(event.currentTarget);

    try {
      const result = await apiRequest("/donor/profile", {
        method: "PATCH",
        token,
        body: {
          consent_to_contact: form.get("consent_to_contact") === "on",
          availability: form.get("availability"),
        },
      });
      setUser((current) =>
        current && result.donor ? { ...current, donor: result.donor } : current,
      );
      setMessage(text.savePreferences);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : text.networkError);
    } finally {
      setPending(false);
    }
  }

  async function reviewDonor(donorId: number) {
    if (!token) return;
    setPending(true);
    setError("");
    try {
      await apiRequest(`/operations/donors/${donorId}/verification`, {
        method: "PATCH",
        token,
        body: {
          account_verified: true,
          medical_eligibility_verified: true,
          availability: "available",
        },
      });
      setDonorsForReview((current) =>
        current.map((donor) =>
          donor.id === donorId
            ? {
                ...donor,
                account_verified: true,
                medical_eligibility_verified: true,
                availability: "available",
              }
            : donor,
        ),
      );
      setMessage(text.reviewDonor);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : text.networkError);
    } finally {
      setPending(false);
    }
  }

  return (
    <main className="site-shell" dir={direction} lang={locale}>
      <header className="topbar">
        <a className="brand" href="#home" aria-label="Rifaq home">
          <span className="brand-mark" aria-hidden="true">
            r
          </span>
          <span>rifaq</span>
        </a>
        <nav className="top-nav" aria-label="Main navigation">
          <a href="#network">{text.network}</a>
          <a href="#account">{text.account}</a>
        </nav>
        <div className="language-picker">
          <label htmlFor="language">{text.selectLanguage}</label>
          <select
            id="language"
            value={locale}
            onChange={(event) => setLocale(event.target.value as Locale)}
          >
            <option value="ar">العربية</option>
            <option value="fr">Français</option>
            <option value="en">English</option>
          </select>
        </div>
      </header>

      <section className="hero" id="home">
        <div className="hero-copy">
          <div className="eyebrow">
            <span className="eyebrow-dot" />
            {text.eyebrow}
          </div>
          <h1>{text.title}</h1>
          <p className="hero-intro">{text.intro}</p>
          <div className="hero-actions">
            <a className="button button-primary" href="#account" onClick={() => setRole("donor")}>
              {text.donate}
              <span aria-hidden="true">↗</span>
            </a>
            <a className="button button-secondary" href="#account" onClick={() => setRole("recipient")}>
              {text.request}
              <span aria-hidden="true">↗</span>
            </a>
          </div>
          <div
            className={`service-state service-${connection}`}
            role="status"
            aria-live="polite"
          >
            <span className="service-dot" />
            {connection === "ready"
              ? text.apiOnline
              : connection === "checking"
                ? text.apiChecking
                : text.apiOffline}
          </div>
        </div>
        <div className="hero-art" aria-label={text.locationsNote}>
          <div className="art-orbit orbit-one" />
          <div className="art-orbit orbit-two" />
          <div className="art-cross">
            <span />
            <i />
          </div>
          <div className="art-label label-top">01 — 58</div>
          <div className="art-label label-bottom">DONATE · CONNECT · CARE</div>
          <div className="art-vertical">ALGERIA · 2026</div>
        </div>
      </section>

      <section className="location-strip" aria-label={text.locations}>
        <div className="location-count">
          <strong>{references?.wilayas.length ?? "—"}</strong>
          <span>{text.locations}</span>
        </div>
        <div className="location-note">
          <span className="note-mark">i</span>
          <span>{references?.geographic_dataset_notice ?? text.locationsNote}</span>
        </div>
      </section>

      <section className="network-section" id="network">
        <div className="section-heading">
          <div>
            <p className="section-kicker">01 / {text.network}</p>
            <h2>{text.network}</h2>
          </div>
          <p>{text.networkText}</p>
        </div>
        <div className="feature-grid">
          <article className="feature-card">
            <span className="feature-index">01</span>
            <span className="feature-icon donor-icon" aria-hidden="true">+</span>
            <h3>{text.donorsTitle}</h3>
            <p>{text.donorsText}</p>
            <a href="#account" onClick={() => setRole("donor")}>{text.donate} <span aria-hidden="true">↗</span></a>
          </article>
          <article className="feature-card feature-card-accent">
            <span className="feature-index">02</span>
            <span className="feature-icon request-icon" aria-hidden="true">↗</span>
            <h3>{text.requestsTitle}</h3>
            <p>{text.requestsText}</p>
            <a href="#account" onClick={() => setRole("recipient")}>{text.request} <span aria-hidden="true">↗</span></a>
          </article>
          <article className="feature-card">
            <span className="feature-index">03</span>
            <span className="feature-icon team-icon" aria-hidden="true">▦</span>
            <h3>{text.teamsTitle}</h3>
            <p>{text.teamsText}</p>
            <span className="feature-tag">STAFF ACCESS ONLY</span>
          </article>
        </div>
      </section>

      <section className="account-section" id="account">
        <div className="account-intro">
          <p className="section-kicker">02 / {text.account}</p>
          <h2>{text.account}</h2>
          <p>{text.intro}</p>
          <div className="account-side-note">
            <span className="side-note-line" />
            <p>{text.disclaimer}</p>
          </div>
        </div>
        <div className="account-card">
          {user ? (
            <div className="signed-in">
              <div className="signed-in-icon" aria-hidden="true">✓</div>
              <p className="signed-in-overline">{text.welcome}</p>
              <h3>{user.name}</h3>
              <p className="signed-in-email">{user.email}</p>
              <p className="profile-guidance">
                {user.role === "donor"
                  ? `${text.donorPending} ${text.accountVerification}: ${user.donor?.account_verified ? "✓" : "—"} · ${text.medicalReview}: ${user.donor?.medical_eligibility_verified ? "✓" : "—"}`
                  : text.recipientReady}
              </p>
              <button className="button button-secondary full-width" onClick={signOut} type="button">
                {text.signOut}
              </button>
              {user.role === "donor" && user.donor && (
                <form className="form-stack request-form" onSubmit={saveDonorPreferences}>
                  <h3>{text.contactPreference}</h3>
                  <p className="profile-guidance">{text.donorEligibilityNote}</p>
                  <label>
                    {text.donorAvailability}
                    <select name="availability" defaultValue={user.donor.availability}>
                      <option value="available">{text.available}</option>
                      <option value="temporarily_unavailable">{text.unavailable}</option>
                      <option value="unavailable">{text.unavailable}</option>
                    </select>
                  </label>
                  <label className="consent-field">
                    <input
                      defaultChecked={user.donor.consent_to_contact}
                      name="consent_to_contact"
                      type="checkbox"
                    />
                    <span>{text.donorContactConsent}</span>
                  </label>
                  <button className="button button-primary full-width" disabled={pending} type="submit">
                    {pending ? text.working : text.savePreferences}
                  </button>
                </form>
              )}
              {user.role === "recipient" && (
                <form className="form-stack request-form" onSubmit={submitBloodRequest}>
                  <h3>{text.requestTitle}</h3>
                  <label>
                    {text.bloodType}
                    <select name="blood_type_id" required defaultValue="">
                      <option value="" disabled>{text.required}</option>
                      {references?.blood_types.map((type) => (
                        <option key={type.id} value={type.id}>{type.code}</option>
                      ))}
                    </select>
                  </label>
                  <label>
                    {text.component}
                    <select name="blood_component_id" required defaultValue="">
                      <option value="" disabled>{text.required}</option>
                      {references?.components.map((component) => (
                        <option key={component.id} value={component.id}>{component.name}</option>
                      ))}
                    </select>
                  </label>
                  <label>
                    {text.wilaya}
                    <select name="wilaya_id" required defaultValue="">
                      <option value="" disabled>{text.chooseWilaya}</option>
                      {wilayaOptions.map((wilaya) => (
                        <option key={wilaya.id} value={wilaya.id}>{wilaya.code} · {wilaya.name}</option>
                      ))}
                    </select>
                  </label>
                  <div className="form-row">
                    <label>
                      {text.units}
                      <input name="units" type="number" min="1" max="20" defaultValue="1" required />
                    </label>
                    <label>
                      {text.urgency}
                      <select name="urgency" defaultValue="routine">
                        <option value="routine">{text.routine}</option>
                        <option value="urgent">{text.urgent}</option>
                        <option value="emergency">{text.emergency}</option>
                      </select>
                    </label>
                  </div>
                  <label>
                    {text.neededBy}
                    <input
                      name="needed_by"
                      ref={neededByRef}
                      type="datetime-local"
                      required
                    />
                  </label>
                  <button className="button button-primary full-width" disabled={pending} type="submit">
                    {pending ? text.working : text.submitRequest}
                  </button>
                </form>
              )}
              {["staff", "admin"].includes(user.role) && (
                <section className="operations-panel" aria-label="Operations workspace">
                  <div className="operations-heading">
                    <p className="section-kicker">STAFF / {user.role.toUpperCase()}</p>
                    <h3>Facility operations</h3>
                    <p>Live records from authorized facility assignments. Stock values count unexpired available units only.</p>
                  </div>
                  {operations && (
                    <div className="operations-metrics">
                      <div><strong>{operations.verified_facilities}</strong><span>Verified facilities</span></div>
                      <div><strong>{operations.available_units}</strong><span>Available units</span></div>
                      <div><strong>{operations.eligible_donor_profiles}</strong><span>Reviewed donor profiles</span></div>
                    </div>
                  )}
                  <div className="operations-heading">
                    <h3>Requests awaiting action</h3>
                  </div>
                  {operationsRequests.length ? (
                    <div className="operations-list">
                      {operationsRequests.map((record) => (
                        <article className="operations-request" key={record.id}>
                          <div className="operations-request-top">
                            <strong>#{record.id} · {record.blood_type?.code ?? "—"} / {record.component?.code ?? "—"}</strong>
                            <span className={`request-state state-${record.status}`}>{record.status}</span>
                          </div>
                          <p>{record.recipient?.first_name} {record.recipient?.last_name} · {record.urgency} · {record.units} units</p>
                          <p>{record.facility?.name ?? "Facility to be assigned"} · {new Date(record.needed_by).toLocaleString(locale)}</p>
                          {record.status === "pending_review" && (
                            <div className="request-actions">
                              <button disabled={pending} onClick={() => void reviewRequest(record.id, "approve")} type="button">Approve</button>
                              <button disabled={pending} onClick={() => void reviewRequest(record.id, "reject")} type="button">Reject</button>
                            </div>
                          )}
                          {record.status === "open" && record.component?.code === "RBC" && (
                            <button className="request-fulfill" disabled={pending} onClick={() => void findDonors(record.id)} type="button">
                              Find consenting, staff-reviewed donor candidates
                            </button>
                          )}
                          {matchedDonors[record.id] && (
                            <div className="donor-matches">
                              <p>Screening aid only: qualified staff must verify eligibility, compatibility and clinical suitability.</p>
                              {matchedDonors[record.id].length ? matchedDonors[record.id].map((donor) => (
                                <p className="donor-match" key={donor.id}>
                                  <span>{donor.first_name} {donor.last_name} · {donor.blood_type}</span>
                                  <a href={`tel:${donor.phone}`}>{donor.phone}</a>
                                </p>
                              )) : <p>No matching profiles were found in your assigned area.</p>}
                            </div>
                          )}
                          {["open", "partially_fulfilled"].includes(record.status) && (
                            <button className="request-fulfill" disabled={pending} onClick={() => void fulfillRequest(record)} type="button">
                              Fulfill one unit from verified local stock
                            </button>
                          )}
                        </article>
                      ))}
                    </div>
                  ) : (
                    <p className="connection-help">No requests are available for your assigned area.</p>
                  )}
                  <div className="operations-heading donor-review-heading">
                    <h3>{text.donorsToReview}</h3>
                    <p>{text.staffReviewWarning}</p>
                  </div>
                  {donorsForReview.length ? (
                    <div className="operations-list">
                      {donorsForReview.map((donor) => (
                        <article className="operations-request" key={donor.id}>
                          <div className="operations-request-top">
                            <strong>#{donor.id} · {donor.first_name} {donor.last_name} · {donor.blood_type ?? "—"}</strong>
                            <span className="request-state">{donor.wilaya.code} · {donor.wilaya.name_fr}</span>
                          </div>
                          <p>{text.accountVerification}: {donor.account_verified ? "✓" : "—"} · {text.medicalReview}: {donor.medical_eligibility_verified ? "✓" : "—"}</p>
                          <p>Contact consent: {donor.consent_to_contact ? "✓" : "—"} · {donor.availability}</p>
                          {donor.consent_to_contact && (!donor.account_verified || !donor.medical_eligibility_verified || donor.availability !== "available") && (
                            <button className="request-fulfill" disabled={pending} onClick={() => void reviewDonor(donor.id)} type="button">
                              {text.reviewDonor}
                            </button>
                          )}
                        </article>
                      ))}
                    </div>
                  ) : (
                    <p className="connection-help">{text.noDonorsToReview}</p>
                  )}
                  {!!inventory.length && (
                    <div className="inventory-list">
                      <h3>Available inventory</h3>
                      {inventory.slice(0, 6).map((stock, index) => (
                        <p key={`${stock.facility_name}-${stock.blood_type}-${stock.component}-${index}`}>
                          <span>{stock.facility_name} · {stock.blood_type} · {stock.component}</span>
                          <strong>{stock.units}</strong>
                        </p>
                      ))}
                    </div>
                  )}
                </section>
              )}
            </div>
          ) : (
            <>
              <div className="form-tabs" role="tablist" aria-label={text.account}>
                <button
                  aria-selected={mode === "register"}
                  className={mode === "register" ? "form-tab active" : "form-tab"}
                  onClick={() => setMode("register")}
                  role="tab"
                  type="button"
                >
                  {text.register}
                </button>
                <button
                  aria-selected={mode === "login"}
                  className={mode === "login" ? "form-tab active" : "form-tab"}
                  onClick={() => setMode("login")}
                  role="tab"
                  type="button"
                >
                  {text.signIn}
                </button>
              </div>
              <form className="form-stack" onSubmit={submitAccount}>
                {mode === "register" && (
                  <>
                    <div className="role-switch" role="group" aria-label={text.account}>
                      <button className={role === "donor" ? "role-option selected" : "role-option"} onClick={() => setRole("donor")} type="button">{text.donor}</button>
                      <button className={role === "recipient" ? "role-option selected" : "role-option"} onClick={() => setRole("recipient")} type="button">{text.recipient}</button>
                    </div>
                    <label>
                      {text.fullName}
                      <input autoComplete="name" name="name" required />
                    </label>
                    <div className="form-row">
                      <label>
                        {text.firstName}
                        <input autoComplete="given-name" name="first_name" required />
                      </label>
                      <label>
                        {text.lastName}
                        <input autoComplete="family-name" name="last_name" required />
                      </label>
                    </div>
                    <label>
                      {text.phone}
                      <input autoComplete="tel" name="phone" type="tel" required />
                    </label>
                    <label>
                      {text.wilaya}
                      <select name="wilaya_id" required={role === "donor"} defaultValue="">
                        <option value="" disabled>{text.chooseWilaya}</option>
                        {wilayaOptions.map((wilaya) => (
                          <option key={wilaya.id} value={wilaya.id}>{wilaya.code} · {wilaya.name}</option>
                        ))}
                      </select>
                    </label>
                    {role === "donor" && (
                      <>
                        <label>
                          {text.bloodType}
                          <select name="blood_type_id" defaultValue="">
                            <option value="">{text.unknown}</option>
                            {references?.blood_types.map((type) => (
                              <option key={type.id} value={type.id}>{type.code}</option>
                            ))}
                          </select>
                        </label>
                        <label className="consent-field">
                          <input name="consent_to_contact" type="checkbox" />
                          <span>{text.contactConsent}</span>
                        </label>
                      </>
                    )}
                  </>
                )}
                <label>
                  {text.email}
                  <input autoComplete="email" name="email" type="email" required />
                </label>
                <label>
                  {text.password}
                  <input
                    autoComplete={mode === "register" ? "new-password" : "current-password"}
                    minLength={mode === "register" ? 12 : 1}
                    name="password"
                    type="password"
                    required
                  />
                </label>
                {error && <p className="form-alert error-alert" role="alert">{error}</p>}
                {message && <p className="form-alert success-alert" role="status">{message}</p>}
                <button className="button button-primary full-width" disabled={pending} type="submit">
                  {pending ? text.working : mode === "register" ? text.create : text.continue}
                </button>
                {connection === "offline" && <p className="connection-help">{text.networkError}</p>}
                {!references?.wilayas.length && <p className="connection-help">{text.noWilayas}</p>}
              </form>
            </>
          )}
          {error && user && <p className="form-alert error-alert" role="alert">{error}</p>}
          {message && user && <p className="form-alert success-alert" role="status">{message}{requestNumber ? ` #${requestNumber}` : ""}</p>}
        </div>
      </section>

      <footer className="site-footer">
        <a className="brand footer-brand" href="#home">
          <span className="brand-mark" aria-hidden="true">r</span>
          <span>rifaq</span>
        </a>
        <p>{text.footer}</p>
        <span className="footer-year">ALGERIA · 2026</span>
      </footer>
    </main>
  );
}
