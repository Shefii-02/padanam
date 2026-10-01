import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/theme/app_theme.dart';
import '../../../../core/utils/json.dart';
import '../../../../core/widgets/ui.dart';
import '../../application/setup_controller.dart';
import '../setup_screen.dart';

SetupController _c(WidgetRef ref) => ref.read(setupControllerProvider.notifier);

// ---------------- Step 1: avatar ----------------
List<Widget> avatarStep(BuildContext context, WidgetRef ref, Json o) {
  final d = ref.watch(setupControllerProvider);
  final avatars = [for (final a in (o['avatars'] as List? ?? const [])) if (a is List && a.length > 1) a];
  final current = avatars.firstWhere((a) => a[0] == d.avatar, orElse: () => avatars.isEmpty ? const ['🧑‍🎓', '#E3E8FA'] : avatars.first);

  Future<void> pick(ImageSource src) async {
    try {
      final f = await ImagePicker().pickImage(source: src, maxWidth: 600, imageQuality: 85);
      if (f != null) {
        final bytes = await f.readAsBytes();
        _c(ref).update((x) => x.copyWith(photo: bytes));
      }
    } catch (_) {
      if (context.mounted) showSoon(context, 'Could not open ${src == ImageSource.camera ? 'camera' : 'gallery'}');
    }
  }

  final canCamera = !kIsWeb && (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS);

  return [
    ...stepHeader(context, 'Pick your avatar', 'This is how other students see you on the leaderboard.'),
    Center(
      child: Container(
        width: 128,
        height: 128,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(shape: BoxShape.circle, color: hex('${current[1]}'), border: Border.all(color: context.palette.card, width: 6)),
        alignment: Alignment.center,
        child: d.photo != null ? Image.memory(d.photo!, fit: BoxFit.cover, width: 128, height: 128) : Text(d.avatar, style: const TextStyle(fontSize: 64)),
      ),
    ),
    const SizedBox(height: 20),
    GridView.count(
      crossAxisCount: 4,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      children: [
        for (final a in avatars)
          InkWell(
            customBorder: const CircleBorder(),
            onTap: () => _c(ref).update((x) => x.copyWith(avatar: '${a[0]}', clearPhoto: true)),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 150),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: hex('${a[1]}'),
                border: Border.all(color: d.photo == null && d.avatar == a[0] ? AppColors.primary2 : Colors.transparent, width: 3),
              ),
              child: Text('${a[0]}', style: const TextStyle(fontSize: 30)),
            ),
          ),
      ],
    ),
    const SizedBox(height: 18),
    Row(children: [
      Expanded(child: AppButton('Upload photo', emoji: '📁', outlined: true, color: AppColors.primary2, onPressed: () => pick(ImageSource.gallery))),
      if (canCamera) ...[
        const SizedBox(width: 10),
        Expanded(child: AppButton('Take photo', emoji: '📷', outlined: true, color: AppColors.primary2, onPressed: () => pick(ImageSource.camera))),
      ],
    ]),
  ];
}

// ---------------- Step 2: name & gender ----------------
List<Widget> nameStep(BuildContext context, WidgetRef ref, Json o) {
  final d = ref.watch(setupControllerProvider);
  final genders = o.ls('genders');
  return [
    ...stepHeader(context, "What's your name?", 'Use the name on your certificates. It appears on your score cards.'),
    fieldLabel('Full name'),
    TextFormField(
      initialValue: d.name,
      textCapitalization: TextCapitalization.words,
      autofillHints: const [AutofillHints.name],
      decoration: inputDeco(context, hint: 'e.g. Sanju Kumar'),
      onChanged: (v) => _c(ref).update((x) => x.copyWith(name: v)),
    ),
    const SizedBox(height: 14),
    fieldLabel('Gender'),
    Segmented(items: genders, selected: genders.indexOf(d.gender ?? ''), onTap: (i) => _c(ref).update((x) => x.copyWith(gender: genders[i]))),
    const SizedBox(height: 14),
    fieldLabel('Email', '(optional)'),
    TextFormField(
      initialValue: d.email,
      keyboardType: TextInputType.emailAddress,
      decoration: inputDeco(context, hint: 'you@example.com'),
      onChanged: (v) => _c(ref).update((x) => x.copyWith(email: v)),
    ),
    const SizedBox(height: 12),
    Text('Gender is used only for physical-test standards and category-wise ranks.', style: TextStyle(fontSize: 12, color: context.palette.muted)),
  ];
}

// ---------------- Step 3: age ----------------
const _months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

List<Widget> ageStep(BuildContext context, WidgetRef ref, Json o) {
  final d = ref.watch(setupControllerProvider);
  final p = context.palette;
  final year = DateTime.now().year;
  void set({int? day, int? month, int? y}) {
    final m = month ?? d.dob.month;
    final yy = y ?? d.dob.year;
    final maxDay = DateTime(yy, m + 1, 0).day;
    _c(ref).update((x) => x.copyWith(dob: DateTime(yy, m, (day ?? d.dob.day).clamp(1, maxDay))));
  }

  Widget dd(int value, List<int> items, String Function(int) label, ValueChanged<int> on) => DropdownButtonFormField<int>(
        key: ValueKey('$value-${items.first}'),
        initialValue: value,
        isExpanded: true,
        decoration: inputDeco(context),
        items: [for (final i in items) DropdownMenuItem(value: i, child: Text(label(i)))],
        onChanged: (v) {
          if (v != null) on(v);
        },
      );

  return [
    ...stepHeader(context, 'When were you born?', "Every exam has an age limit. We'll check it for you."),
    Row(children: [
      Expanded(flex: 3, child: dd(d.dob.day, List.generate(31, (i) => i + 1), (v) => '$v', (v) => set(day: v))),
      const SizedBox(width: 8),
      Expanded(flex: 5, child: dd(d.dob.month, List.generate(12, (i) => i + 1), (v) => _months[v - 1], (v) => set(month: v))),
      const SizedBox(width: 8),
      Expanded(flex: 4, child: dd(d.dob.year, List.generate(45, (i) => year - 16 - i), (v) => '$v', (v) => set(y: v))),
    ]),
    const SizedBox(height: 18),
    Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(22), gradient: const LinearGradient(colors: [AppColors.primary, Color(0xFF3B4FD8)])),
      child: Row(children: [
        Text('${d.age}', style: const TextStyle(color: Colors.white, fontSize: 48, fontWeight: FontWeight.w800, height: 1)),
        const SizedBox(width: 16),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('years old', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700)),
          Text('Born ${d.dob.day} ${_months[d.dob.month - 1]} ${d.dob.year}', style: const TextStyle(color: Colors.white70, fontSize: 12.5)),
        ]),
      ]),
    ),
    const SizedBox(height: 20),
    const Text('Common age limits (general category)', style: TextStyle(fontWeight: FontWeight.w800)),
    const SizedBox(height: 10),
    AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
      child: DividedColumn(children: [
        for (final l in o.l('age_limits'))
          Builder(builder: (_) {
            final min = l['min'] is num ? (l['min'] as num).toInt() : null;
            final max = l['max'] is num ? (l['max'] as num).toInt() : null;
            final ok = max == null || (d.age >= (min ?? 0) && d.age <= max);
            return ListRow(
              padding: const EdgeInsets.symmetric(vertical: 10),
              leading: CircleAvatar(
                radius: 11,
                backgroundColor: ok ? AppColors.green : AppColors.red,
                child: Icon(ok ? Icons.check_rounded : Icons.close_rounded, size: 14, color: Colors.white),
              ),
              title: l.s('name'),
              trailing: Text(max == null ? 'No upper limit' : '$min–$max yrs', style: TextStyle(fontSize: 12, color: p.muted)),
            );
          }),
      ]),
    ),
    const SizedBox(height: 10),
    Text('Reserved categories get relaxation. Always confirm in the official notification.', style: TextStyle(fontSize: 11.5, color: p.muted)),
  ];
}

// ---------------- Step 4: place ----------------
List<Widget> placeStep(BuildContext context, WidgetRef ref, Json o) => [_PlaceStep(districts: o.ls('districts'))];

class _PlaceStep extends ConsumerStatefulWidget {
  const _PlaceStep({required this.districts});
  final List<String> districts;

  @override
  ConsumerState<_PlaceStep> createState() => _PlaceStepState();
}

class _PlaceStepState extends ConsumerState<_PlaceStep> {
  String _q = '';
  bool _locating = false;
  late final _town = TextEditingController(text: ref.read(setupControllerProvider).town);
  late final _pin = TextEditingController(text: ref.read(setupControllerProvider).pincode);

  @override
  void dispose() {
    _town.dispose();
    _pin.dispose();
    super.dispose();
  }

  Future<void> _locate() async {
    setState(() => _locating = true);
    await Future<void>.delayed(const Duration(milliseconds: 900)); // plug geolocator in later
    _town.text = 'Irinjalakuda';
    _pin.text = '680121';
    _c(ref).update((x) => x.copyWith(district: 'Thrissur', town: 'Irinjalakuda', pincode: '680121', outsideKerala: false));
    if (mounted) setState(() => _locating = false);
  }

  @override
  Widget build(BuildContext context) {
    final d = ref.watch(setupControllerProvider);
    final list = widget.districts.where((x) => x.toLowerCase().contains(_q.toLowerCase())).toList();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      ...stepHeader(context, 'Where do you live?', 'For district-wise ranks and nearby exam centres.'),
      AppCard(
        color: AppColors.mint,
        bordered: false,
        onTap: _locating ? null : _locate,
        child: Row(children: [
          const Text('📍', style: TextStyle(fontSize: 24)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(_locating ? 'Finding your location…' : 'Use my current location', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0D4A32))),
              const Text('Fills district and town automatically', style: TextStyle(fontSize: 12, color: Color(0xFF2E6B52))),
            ]),
          ),
          if (_locating) const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
        ]),
      ),
      const SizedBox(height: 14),
      TextField(decoration: inputDeco(context, hint: 'Search district', prefix: const Icon(Icons.search_rounded)), onChanged: (v) => setState(() => _q = v)),
      const SizedBox(height: 10),
      Opacity(
        opacity: d.outsideKerala ? 0.4 : 1,
        child: Wrap(spacing: 8, runSpacing: 8, children: [
          for (final x in list)
            ChoiceChip(
              label: Text(x),
              selected: d.district == x && !d.outsideKerala,
              onSelected: (_) => _c(ref).update((s) => s.copyWith(district: x, outsideKerala: false)),
            ),
          if (list.isEmpty) Text('No district matches', style: TextStyle(color: context.palette.muted)),
        ]),
      ),
      CheckboxListTile(
        contentPadding: EdgeInsets.zero,
        controlAffinity: ListTileControlAffinity.leading,
        value: d.outsideKerala,
        title: const Text('I live outside Kerala', style: TextStyle(fontWeight: FontWeight.w600)),
        onChanged: (v) => _c(ref).update((s) => s.copyWith(outsideKerala: v ?? false)),
      ),
      if (d.outsideKerala) ...[
        fieldLabel('State'),
        TextFormField(initialValue: d.state, decoration: inputDeco(context, hint: 'e.g. Tamil Nadu'), onChanged: (v) => _c(ref).update((s) => s.copyWith(state: v))),
        const SizedBox(height: 12),
      ],
      Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            fieldLabel('Town / Panchayat', '(optional)'),
            TextField(controller: _town, decoration: inputDeco(context, hint: 'e.g. Irinjalakuda'), onChanged: (v) => _c(ref).update((s) => s.copyWith(town: v))),
          ]),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            fieldLabel('PIN code', '(optional)'),
            TextField(
              controller: _pin,
              keyboardType: TextInputType.number,
              maxLength: 6,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: inputDeco(context, hint: '680121').copyWith(counterText: ''),
              onChanged: (v) => _c(ref).update((s) => s.copyWith(pincode: v)),
            ),
          ]),
        ),
      ]),
    ]);
  }
}
